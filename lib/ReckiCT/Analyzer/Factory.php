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
 */

namespace ReckiCT\Analyzer;

class Factory
{
    public static function analyzer()
    {
        $signatureResolver = new SignatureResolver();

        $analyzer = new Analyzer();

        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new AstProcessor\AssignOpResolver());
        $traverser->addVisitor(new AstProcessor\ReferenceKiller()); // after AssignOpResolver, before LoopResolver
        $analyzer->addTraverser($traverser);

        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new AstProcessor\LoopResolver());
        $traverser->addVisitor(new AstProcessor\ElseIfResolver());
        $traverser->addVisitor(new AstProcessor\SignatureResolver($signatureResolver));
        $traverser->addVisitor(new AstProcessor\RecursionResolver());
        $analyzer->addTraverser($traverser);

        $analyzer->addProcessor(new GraphProcessor\SSACompiler());
        $resolver = new GraphProcessor\Optimizer();

        $resolver->addRule(new OptimizerRule\Assign());
        $resolver->addRule(new OptimizerRule\BinaryOp());
        $resolver->addRule(new OptimizerRule\BooleanNot());
        $resolver->addRule(new OptimizerRule\BitwiseNot());
        $resolver->addRule(new OptimizerRule\ConstBinaryOp());
        $resolver->addRule(new OptimizerRule\DeadAssignmentRemover());
        $resolver->addRule(new OptimizerRule\FunctionCall());
        $resolver->addRule(new OptimizerRule\Phi());
        $resolver->addRule(new OptimizerRule\UnreachableCode());

        $analyzer->addProcessor($resolver);

        $analyzer->addProcessor(new GraphProcessor\PhiResolver());

        return $analyzer;
    }

}
