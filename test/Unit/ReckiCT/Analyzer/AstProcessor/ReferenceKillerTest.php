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
 * @category Tests
 * @package Analyzer
 * @subpackage AstProcessor
 */

namespace ReckiCT\Analyzer\AstProcessor;

use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPUnit_Framework_TestCase as TestCase;
use PhpParser\NodeTraverser;

use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Param;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Variable;

/**
 * @coversDefaultClass ReckiCT\Analyzer\AstProcessor\ReferenceKiller
 */
class ReferenceKillerTest extends TestCase
{
    protected $traverser;

    protected function setUp()
    {
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new ReferenceKiller());
    }

    /**
     * @covers ::enterNode
     */
    public function testNormalFunction()
    {
        $from = new Function_('foo', ['stmts' => [new Variable('a')]]);
        $this->assertEquals([$from], $this->traverser->traverse([$from]));
    }

    /**
     * @expectedException LogicException
     * @covers ::enterNode
     */
    public function testKillFunction()
    {
        $this->traverser->traverse([
            new Function_('foo', ['byRef' => true]),
        ]);
    }

    /**
     * @expectedException LogicException
     * @covers ::enterNode
     */
    public function testKillFunctionParam()
    {
        $this->traverser->traverse([
            new Function_('foo', ['params' => [new Param('foo', null, null, true)]]),
        ]);
    }

    /**
     * @covers ::enterNode
     */
    public function testAssignByRef()
    {
        $from = new Function_('foo', ['stmts' => [
            new AssignRef(new Variable('a'), new Variable('b')),
            new AssignRef(new Variable('a'), new Variable('c')),
        ]]);

        $to = new Function_('foo', ['stmts' => [
            new Assign(new Variable('a'), new LNumber(0)),
            new Assign(new Variable('a'), new LNumber(1)),
            new Assign(new Variable('a'), new LNumber(2)),
        ]]);


        $this->assertEquals([$to], ReferenceKillerTestAstCleaner::clean($this->traverser->traverse([$from])));
    }


    public function testSimpleFetchByRef() {
        $from = new Function_('foo', ['stmts' => [
            new Assign(new Variable('a'), new LNumber(0)),
            new AssignRef(new Variable('a'), new Variable('b')),
            new Assign(new Variable('c'), new Variable('a')),
        ]]);

        $to = new Function_('foo', ['stmts' => [
            new Assign(new Variable('a'), new LNumber(0)),
            new Node\Stmt\Switch_(new Variable('a'), [
                new Node\Stmt\Case_(new LNumber(0), [
                    new Assign(new Variable('temporary_variable_assignRef_ReckiCT_0'), new LNumber(0)),
                    new Node\Stmt\Break_,
                ]),
                new Node\Stmt\Case_(new LNumber(1), [
                    new Assign(new Variable('b'), new LNumber(0)),
                    new Node\Stmt\Break_,
                ]),
            ]),
            new Assign(new Variable('a'), new LNumber(1)),
            new Node\Stmt\Switch_(new Variable('a'), [
                new Node\Stmt\Case_(new LNumber(0), [
                    new Assign(new Variable('temporary_variable_assignRef_ReckiCT_1'), new Variable('temporary_variable_assignRef_ReckiCT_0')),
                    new Node\Stmt\Break_,
                ]),
                new Node\Stmt\Case_(new LNumber(1), [
                    new Assign(new Variable('temporary_variable_assignRef_ReckiCT_1'), new Variable('b')),
                    new Node\Stmt\Break_,
                ]),
            ]),
            new Assign(new Variable('c'), new Variable('temporary_variable_assignRef_ReckiCT_1')),
        ]]);

        $this->assertEquals([$to], ReferenceKillerTestAstCleaner::clean($this->traverser->traverse([$from])));
    }

    public function testDimFetchByRef() {
        $from = new Function_('foo', ['stmts' => [
            new AssignRef(new Variable('a'), new ArrayDimFetch(new PropertyFetch(new Variable('b'), new FuncCall(new Name('c'))), new LNumber(0))),
            new Assign(new Variable('a'), new LNumber(0)),
            new Assign(new Variable('d'), new Variable('a')),
        ]]);

        $to = new Function_('foo', ['stmts' => [
            new Assign(new Variable('a'), new LNumber(0)),
            new Assign(new Variable('temporary_variable_assignRef_ReckiCT_1'), new FuncCall(new Name('c'))),
            new Assign(new Variable('a'), new LNumber(1)),
            new Node\Stmt\Switch_(new Variable('a'), [
                new Node\Stmt\Case_(new LNumber(0), [
                    new Assign(new Variable('temporary_variable_assignRef_ReckiCT_0'), new LNumber(0)),
                    new Node\Stmt\Break_,
                ]),
                new Node\Stmt\Case_(new LNumber(1), [
                    new Assign(new ArrayDimFetch(new PropertyFetch(new Variable('b'), new Variable('temporary_variable_assignRef_ReckiCT_1')), new LNumber(0)), new LNumber(0)),
                    new Node\Stmt\Break_,
                ]),
            ]),
            new Node\Stmt\Switch_(new Variable('a'), [
                new Node\Stmt\Case_(new LNumber(0), [
                    new Assign(new Variable('temporary_variable_assignRef_ReckiCT_2'), new Variable('temporary_variable_assignRef_ReckiCT_0')),
                    new Node\Stmt\Break_,
                ]),
                new Node\Stmt\Case_(new LNumber(1), [
                    new Assign(new Variable('temporary_variable_assignRef_ReckiCT_2'), new ArrayDimFetch(new PropertyFetch(new Variable('b'), new Variable('temporary_variable_assignRef_ReckiCT_1')), new LNumber(0))),
                    new Node\Stmt\Break_,
                ]),
            ]),
            new Assign(new Variable('d'), new Variable('temporary_variable_assignRef_ReckiCT_2')),
        ]]);

        $this->assertEquals([$to], ReferenceKillerTestAstCleaner::clean($this->traverser->traverse([$from])));
    }
}

class ReferenceKillerTestAstCleaner extends NodeVisitorAbstract {
    public static function clean(array $node) {
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new self());
        return $traverser->traverse($node);
    }

    public function enterNode(Node $node) {
        $prop = (new \ReflectionClass($node))->getProperty('attributes');
        $prop->setAccessible(true);
        $attrs = $prop->getValue($node);
        unset($attrs['referencing_var']);
        unset($attrs['stmt_node']);
        $prop->setValue($node, $attrs);
    }
}