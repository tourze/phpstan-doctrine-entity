<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests;

use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanDoctrineEntity\EntityRepositoryClassConstantRule;

/**
 * @extends RuleTestCase<EntityRepositoryClassConstantRule>
 * @internal
 */
#[CoversClass(EntityRepositoryClassConstantRule::class)]
final class EntityRepositoryClassConstantRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan.neon'];
    }

    public function testGetNodeTypeShouldReturnInClassNode(): void
    {
        $rule = new EntityRepositoryClassConstantRule();
        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    public function testProcessNodeShouldAnalyze(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $this->assertIsArray($errors);
    }

    protected function getRule(): Rule
    {
        return new EntityRepositoryClassConstantRule();
    }
}
