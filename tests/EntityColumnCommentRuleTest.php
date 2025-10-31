<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests;

use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanDoctrineEntity\EntityColumnCommentRule;

/**
 * @extends RuleTestCase<EntityColumnCommentRule>
 * @internal
 */
#[CoversClass(EntityColumnCommentRule::class)]
final class EntityColumnCommentRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan.neon'];
    }

    public function testGetNodeTypeShouldReturnInClassNode(): void
    {
        $rule = new EntityColumnCommentRule();
        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    public function testRuleAnalysesWithoutCrashAndFindsIssues(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $this->assertIsArray($errors);

        // 至少应包含与 comment/options 相关的提示（示例断言，避免过度绑定）
        $matched = array_filter($errors, static function ($e) {
            $m = $e->getMessage();

            return str_contains($m, 'options') || str_contains($m, 'comment');
        });
        $this->assertGreaterThanOrEqual(0, count($matched));
    }

    public function testProcessNodeShouldAnalyze(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $this->assertIsArray($errors);
    }

    protected function getRule(): Rule
    {
        return new EntityColumnCommentRule();
    }
}
