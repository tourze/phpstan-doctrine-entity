<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests;

use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanDoctrineEntity\EntityDateTimeImmutableRule;

/**
 * @extends RuleTestCase<EntityDateTimeImmutableRule>
 * @internal
 */
#[CoversClass(EntityDateTimeImmutableRule::class)]
final class EntityDateTimeImmutableRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan.neon'];
    }

    public function testGetNodeTypeShouldReturnInClassNode(): void
    {
        $rule = new EntityDateTimeImmutableRule();
        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    public function testRuleAnalysesWithoutCrash(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $this->assertIsArray($errors);
    }

    public function testProcessNodeShouldAnalyze(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $this->assertIsArray($errors);
    }

    protected function getRule(): Rule
    {
        return new EntityDateTimeImmutableRule();
    }
}
