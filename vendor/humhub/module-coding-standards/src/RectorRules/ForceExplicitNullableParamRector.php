<?php

namespace HumHubUtils\RectorRules;

use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\NullableType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

final class ForceExplicitNullableParamRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Param::class];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add explicit nullable type (?Type) when parameter default value is null',
            [
                new CodeSample(
                    <<<'CODE'
function test(Type $param = null) {}
CODE
                    ,
                    <<<'CODE'
function test(?Type $param = null) {}
CODE,
                ),
            ],
        );
    }

    public function refactor(Node $node): ?Node
    {
        // Type must exist and be a Node
        if (!$node->type instanceof Node) {
            return null;
        }

        // Default value must be defined as `null`
        if (!$node->default instanceof Node || !$this->nodeComparator->areNodesEqual(
            $node->default,
            new Node\Expr\ConstFetch(new Node\Name('null')),
        )) {
            return null;
        }

        // Skip if the param is already nullable
        if ($node->type instanceof NullableType) {
            return null;
        }

        // Convert `Type` to `?Type`
        $node->type = new NullableType($node->type);

        return $node;
    }
}
