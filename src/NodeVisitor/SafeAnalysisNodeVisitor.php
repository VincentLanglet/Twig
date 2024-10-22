<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\NodeVisitor;

use Twig\Environment;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\Node;

/**
 * @internal
 */
final class SafeAnalysisNodeVisitor implements NodeVisitorInterface
{
    private array $data = [];
    private array $safeVars = [];

    public function setSafeVars(array $safeVars): void
    {
        $this->safeVars = $safeVars;
    }

    public function getSafe(Node $node)
    {
        $hash = spl_object_hash($node);
        if (!isset($this->data[$hash])) {
            return;
        }

        foreach ($this->data[$hash] as $bucket) {
            if ($bucket['key'] !== $node) {
                continue;
            }

            if (\in_array('html_attr', $bucket['value'])) {
                $bucket['value'][] = 'html';
            }

            return $bucket['value'];
        }
    }

    private function setSafe(Node $node, array $safe): void
    {
        $hash = spl_object_hash($node);
        if (isset($this->data[$hash])) {
            foreach ($this->data[$hash] as &$bucket) {
                if ($bucket['key'] === $node) {
                    $bucket['value'] = $safe;

                    return;
                }
            }
        }
        $this->data[$hash][] = [
            'key' => $node,
            'value' => $safe,
        ];
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof ConstantExpression) {
            // constants are marked safe for all
            $this->setSafe($node, ['all']);
        } elseif ($node instanceof BlockReferenceExpression) {
            // blocks are safe by definition
            $this->setSafe($node, ['all']);
        } elseif ($node instanceof ParentExpression) {
            // parent block is safe by definition
            $this->setSafe($node, ['all']);
        } elseif ($node instanceof ConditionalExpression) {
            // intersect safeness of both operands
            $safe = $this->intersectSafe($this->getSafe($node->getNode('expr2')), $this->getSafe($node->getNode('expr3')));
            $this->setSafe($node, $safe);
        } elseif ($node instanceof FilterExpression) {
            // filter expression is safe when the filter is safe
            if ($filter = $node->getAttribute('twig_callable')) {
                $safe = $filter->getSafe($node->getNode('arguments'));
                if (null === $safe) {
                    $safe = $this->intersectSafe($this->getSafe($node->getNode('node')), $filter->getPreservesSafety());
                }
                $this->setSafe($node, $safe);
            } else {
                $this->setSafe($node, []);
            }
        } elseif ($node instanceof FunctionExpression) {
            // function expression is safe when the function is safe
            if ($function = $node->getAttribute('twig_callable')) {
                $this->setSafe($node, $function->getSafe($node->getNode('arguments')));
            } else {
                $this->setSafe($node, []);
            }
        } elseif ($node instanceof MacroReferenceExpression) {
            // all macro calls are safe
            $this->setSafe($node, ['all']);
        } elseif ($node instanceof GetAttrExpression && $node->getNode('node') instanceof NameExpression) {
            $name = $node->getNode('node')->getAttribute('name');
            if (\in_array($name, $this->safeVars)) {
                $this->setSafe($node, ['all']);
            } else {
                $this->setSafe($node, []);
            }
        } else {
            $this->setSafe($node, []);
        }

        return $node;
    }

    private function intersectSafe(?array $a = null, ?array $b = null): array
    {
        if (null === $a || null === $b) {
            return [];
        }

        if (\in_array('all', $a)) {
            return $b;
        }

        if (\in_array('all', $b)) {
            return $a;
        }

        return array_intersect($a, $b);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
