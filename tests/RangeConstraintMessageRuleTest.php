<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests;

use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanDoctrineEntity\RangeConstraintMessageRule;

/**
 * @extends RuleTestCase<RangeConstraintMessageRule>
 * @internal
 */
#[CoversClass(RangeConstraintMessageRule::class)]
final class RangeConstraintMessageRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan.neon'];
    }

    public function testGetNodeType(): void
    {
        $rule = new RangeConstraintMessageRule();
        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    public function testRuleDetectsInvalidMessages(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/RangeTestFiles.php'], [
            [
                "类 Tourze\PHPStanDoctrineEntity\Tests\Fixtures\InvalidRangeMessage 的属性 \$count 使用 #[Assert\\Range] 同时设置了 min/max 和 minMessage/maxMessage，会触发 ConstraintDefinitionException；请改用 notInRangeMessage。\n    💡 当同时设置 min 与 max 时只能使用 notInRangeMessage，自定义上下限提示请写在该参数。",
                11,
            ],
            [
                "类 Tourze\PHPStanDoctrineEntity\Tests\Fixtures\InvalidRangeBothMessages 的属性 \$score 使用 #[Assert\\Range] 同时设置了 min/max 和 minMessage/maxMessage，会触发 ConstraintDefinitionException；请改用 notInRangeMessage。\n    💡 当同时设置 min 与 max 时只能使用 notInRangeMessage，自定义上下限提示请写在该参数。",
                17,
            ],
        ]);
    }

    public function testRuleIgnoresValidRanges(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/RangeTestFiles.php']);

        foreach ($errors as $error) {
            $this->assertStringNotContainsString('ValidRangeWithNotInRangeMessage', $error->getMessage());
            $this->assertStringNotContainsString('ValidRangeWithSingleBoundMessage', $error->getMessage());
        }
    }

    protected function getRule(): Rule
    {
        return new RangeConstraintMessageRule();
    }
}
