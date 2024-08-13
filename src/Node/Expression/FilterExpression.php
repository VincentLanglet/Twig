<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Node\Expression;

use Twig\Compiler;
use Twig\Node\Node;

class FilterExpression extends CallExpression
{
    public function __construct(Node $node, ConstantExpression $filterName, Node $arguments, int $lineno, ?string $tag = null)
    {
        parent::__construct(['node' => $node, 'filter' => $filterName, 'arguments' => $arguments], ['name' => $filterName->getAttribute('value'), 'type' => 'filter'], $lineno, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        $filter = $compiler->getEnvironment()->getFilter($this->getAttribute('name'));

        $this->setAttribute('needs_charset', $filter->needsCharset());
        $this->setAttribute('needs_environment', $filter->needsEnvironment());
        $this->setAttribute('needs_context', $filter->needsContext());
        $this->setAttribute('arguments', $filter->getArguments());
        $this->setAttribute('callable', $filter->getCallable());
        $this->setAttribute('is_variadic', $filter->isVariadic());
        $this->setAttribute('dynamic_name', $filter->getDynamicName());

        $this->compileCallable($compiler);
    }
}
