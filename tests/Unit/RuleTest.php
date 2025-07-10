<?php

namespace LaravelPropertyBag\tests\Unit;

use PHPUnit\Framework\Attributes\Test;

use File;
use LaravelPropertyBag\tests\TestCase;
use LaravelPropertyBag\Settings\Rules\RuleValidator;

class RuleTest extends TestCase
{
    /**
     */
    #[Test]
    public function rule_validator_can_correctly_identify_rules()
    {
        $validator = new RuleValidator();

        $this->assertEquals('test', $validator->isRule(':test:'));

        $this->assertEquals(
            'test=arg1,arg2',
            $validator->isRule(':test=arg1,arg2:')
        );

        $this->assertFalse($validator->isRule('test'));

        $this->assertFalse($validator->isRule(':test'));

        $this->assertFalse($validator->isRule('test:'));
    }

    /**
     */
    #[Test]
    public function throws_exception_for_rule_not_declared()
    {
        $this->expectException(\LaravelPropertyBag\Exceptions\InvalidSettingsRule::class);
        $this->expectExceptionMessage('Method ruleNope for rule nope not found. Check rule spelling or create method ruleNope in Rules.php.');
        
        $comment = $this->makeComment();
        $comment->settings()->set(['invalid' => 'nope']);
    }

    /**
     */
    #[Test]
    public function any_rule_returns_true_for_any()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('any', 7)
        );
    }

    /**
     */
    #[Test]
    public function alpha_rule_returns_true_for_alpha()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('alpha', 'alpha')
        );
    }

    /**
     */
    #[Test]
    public function alpha_rule_returns_false_for_non_alpha()
    {
        $this->assertFalse(
            $this->makeComment()->settings()->isValid('alpha', false)
        );
    }

    /**
     */
    #[Test]
    public function alphanum_rule_returns_true_for_alphanum()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('alphanum', 'alpha6')
        );
    }

    /**
     */
    #[Test]
    public function alphanum_rule_returns_false_for_non_alphanum()
    {
        $this->assertFalse(
            $this->makeComment()->settings()->isValid('alphanum', false)
        );
    }

    /**
     */
    #[Test]
    public function bool_rule_returns_true_for_bool()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('bool', true)
        );
    }

    /**
     */
    #[Test]
    public function bool_rule_returns_false_for_non_bool()
    {
        $this->assertFalse(
            $this->makeComment()->settings()->isValid('bool', 0)
        );
    }

    /**
     */
    #[Test]
    public function integer_rule_returns_true_for_integer()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('integer', 7)
        );
    }

    /**
     */
    #[Test]
    public function integer_rule_returns_false_for_non_integer()
    {
        $this->assertFalse(
            $this->makeComment()->settings()->isValid('integer', '7')
        );
    }

    /**
     */
    #[Test]
    public function numeric_rule_returns_true_for_numeric()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('numeric', '7')
        );
    }

    /**
     */
    #[Test]
    public function numeric_rule_returns_false_for_non_numeric()
    {
        $this->assertFalse(
            $this->makeComment()->settings()->isValid('numeric', 'test')
        );
    }

    /**
     */
    #[Test]
    public function range_rule_returns_true_for_value_in_range()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('range', '3')
        );
    }

    /**
     */
    #[Test]
    public function range_rule_returns_true_for_value_at_low_end()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('range', '1')
        );
    }

    /**
     */
    #[Test]
    public function range_rule_returns_true_for_value_at_high_end()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('range', 5)
        );
    }

    /**
     */
    #[Test]
    public function range_rule_returns_false_for_value_out_of_range()
    {
        $this->assertFalse(
            $this->makeComment()->settings()->isValid('range', 6)
        );
    }

    /**
     */
    #[Test]
    public function range_rule_handles_negative_numbers()
    {
        $comment = $this->makeComment();

        $this->assertTrue(
            $comment->settings()->isValid('range2', -6)
        );

        $this->assertFalse(
            $comment->settings()->isValid('range2', -16)
        );
    }

    /**
     */
    #[Test]
    public function string_rule_returns_true_for_string()
    {
        $this->assertTrue(
            $this->makeComment()->settings()->isValid('string', 'string')
        );
    }

    /**
     */
    #[Test]
    public function string_rule_returns_false_for_non_string()
    {
        $this->assertFalse(
            $this->makeComment()->settings()->isValid('string', false)
        );
    }

    /**
     */
    #[Test]
    public function rules_can_be_user_defined()
    {
        File::makeDirectory(app_path('Settings'));

        File::makeDirectory(app_path('Settings/Resources'));

        $stub = file_get_contents(
            __DIR__.'/../Classes/Rules.php'
        );

        file_put_contents(app_path('Settings/Resources/Rules.php'), $stub);

        require_once app_path('Settings/Resources/Rules.php');

        $this->assertFileExists(app_path('Settings/Resources/Rules.php'));

        $this->assertTrue(
            $this->makeComment()->settings()->isValid('user_defined', 1)
        );

        $this->assertFalse(
            $this->makeComment()->settings()->isValid('user_defined', 2)
        );

        File::deleteDirectory(app_path('Settings'));
    }
}
