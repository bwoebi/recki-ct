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

use PhpParser\Node\Stmt\Function_ as AstFunction;
use PhpParser\Node;
use PhpParser\NodeTraverser;

use ReckiCT\Graph\Vertex\Function_ as JitFunction;

class Analyzer
{
    protected $traversers = [];
    protected $graphProcessors = [];

    public function addTraverser(NodeTraverser $traverser)
    {
        $this->traversers[] = $traverser;
    }

    public function addProcessor(GraphProcessor $processor)
    {
        $this->graphProcessors[] = $processor;
    }

    public function analyzeFunction(AstFunction $func)
    {
        foreach ($this->traversers as $traverser) {
            list($func) = $traverser->traverse([$func]);
        }

        return $func;
    }

    public function analyzeGraph(JitFunction $func)
    {
        $state = new GraphState($func);
        foreach ($this->graphProcessors as $processor) {
            $processor->process($state);
        }
    }

}
