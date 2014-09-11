<?php
/**
 * Copyright 2014 Google Inc. All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @copyright 2014 Google Inc. All rights reserved
 * @license http://www.apache.org/licenses/LICENSE-2.0.txt Apache-2.0
 * @package Analyzer
 * @subpackage AstProcessor
 */

namespace ReckiCT\Analyzer\AstProcessor;

use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Scalar\LNumber;

class ReferenceKiller extends NodeVisitorAbstract
{
    protected $refs = array();
    protected $map = array();
    protected $dimAssigns = array();
    protected $pass = 2;
    protected $varCounter = 0;

    /**
     * Called when entering a node.
     *
     * Return value semantics:
     *  * null:      $node stays as-is
     *  * otherwise: $node is set to the return value
     *
     * @param Node $node Node
     *
     * @return null|Node Node
     */
    public function enterNode(Node $node)
    {
        if (isset($node->byRef) && $node->byRef) {
            throw new \LogicException("References are not allowed in arrays or function calls");
        }

        if ($this->pass == 1) {
            return $this->firstPass($node);
        } elseif ($this->pass == 2) {
            return $this->secondPass($node);
        }
    }

    protected function dimensionName(Node\Expr $node) {
        /*if ($node instanceof Variable) {
            return "$".$node->name;
        }
        if ($node instanceof Node\Scalar) {
            return $node->val;
        }
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            return $this->dimensionName($node->var)."\xff[".$this->dimensionName($node->dim)."]";
        }*/
        return (new \PhpParser\PrettyPrinter\Standard)->prettyPrint([$node]);
    }

    protected function cloneNode(Node $node) {
        $node = clone $node;
        foreach ($node as &$subnode) {
            if ($subnode instanceof Node) {
                $subnode = $this->cloneNode($subnode);
            }
        }
        return $node;
    }

    // Analysis only pass
    protected function firstPass(Node $node) {
        if ($node instanceof AssignRef) {
            $var = $node->var->name;
            $expr = $this->dimensionName($node->expr);

            if (!isset($this->refs[$var])) {
                $this->refs[$var] = 0;
            }
            $label = $this->refs[$var]++;
            if (!isset($this->map[$var][$expr])) {
                $this->map[$var][$expr] = ["id" => $label, "ast" => $node->expr];
            }
        }

        return null;
    }

    protected function secondPass(Node $node) {
        if ($node instanceof Node\Stmt\Function_) {
            $this->pass = 1;

            $traverser = new \PhpParser\NodeTraverser;
            $traverser->addVisitor($this);
            $traverser->traverse([$node]);

            $this->pass = 2;
            return null;
        }

        // AssignOps must already have been resolved
        if ($node instanceof Assign && $node->var instanceof Variable && isset($this->refs[$node->var->name])) {
            $cases = [];
            foreach ($this->map[$node->var->name] as $label) {
                $cases[] = new Node\Stmt\Case_(new LNumber($label["id"]), [new Assign($this->cloneNode($label["ast"]), $node->expr), new Node\Stmt\Break_]);
            }
            return new Node\Stmt\Switch_($node->var, $cases);
        }
    }

    protected function resolveArrayVariables(Node $node) {
        if ($node instanceof Node\Expr\ArrayDimFetch || $node instanceof Node\Expr\PropertyFetch) {
            if ($node instanceof Node\Expr\ArrayDimFetch) {
                $dim = &$node->dim;
            } else {
                $dim = &$node->name;
            }
            $intern = $this->resolveArrayVariables($node->var);

            if ($dim instanceof Node\Scalar) {
                return $intern;
            } else {
                $variable = new Variable("temporary_variable_assignRef_ReckiCT_".$this->varCounter++);
                $ret = array_merge($intern, [new Assign($this->cloneNode($variable), $dim)]);
                $dim = $variable;
                return $ret;
            }
        }

        return [];
    }

    public function leaveNode(Node $node) {
        if ($this->pass != 2) {
            return null;
        }

        if ($node instanceof AssignRef) {
            $info = $this->map[$node->var->name][$this->dimensionName($node->expr)];
            return array_merge($this->resolveArrayVariables($info["ast"]), [new Assign($node->var, new LNumber($info["id"]))]);
        }
    }
}
