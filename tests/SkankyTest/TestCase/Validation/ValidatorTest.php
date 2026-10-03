<?php

namespace SkankyTest\TestCase\Validation;

use PHPUnit\Framework\TestCase;
use SkankyDev\Validation\Validator;
use SkankyDev\Validation\Rules\Required;
use SkankyDev\Validation\Rules\Email;
use SkankyDev\Validation\Rules\Min;
use SkankyDev\Validation\Rules\Max;
use SkankyDev\Validation\Rules\MinLength;
use SkankyDev\Validation\Rules\MaxLength;
use SkankyDev\Validation\Rules\Numeric;
use SkankyDev\Validation\Rules\Confirmed;
use SkankyDev\Validation\Rules\Same;
use SkankyDev\Validation\Rules\HexColor;
use SkankyDev\Validation\Rules\Regex;

class ValidatorTest extends TestCase
{
    // ── Rule unit tests ────────────────────────────────────────────────────────

    public function testRequiredFailsOnEmpty(): void {
        $rule = new Required();
        $this->assertFalse($rule->check('name', ''));
        $this->assertFalse($rule->check('name', null));
        $this->assertFalse($rule->check('name', []));
        $this->assertTrue($rule->check('name', 'Simon'));
        $this->assertTrue($rule->check('name', 0));
    }

    public function testEmailPassesOnValidAddress(): void {
        $rule = new Email();
        $this->assertTrue($rule->check('mail', 'simon@skankydev.com'));
        $this->assertTrue($rule->check('mail', '')); // empty = optional
        $this->assertFalse($rule->check('mail', 'not-an-email'));
        $this->assertFalse($rule->check('mail', 'missing@tld'));
    }

    public function testMinRule(): void {
        $rule = new Min(5);
        $this->assertTrue($rule->check('qty', 5));
        $this->assertTrue($rule->check('qty', 10));
        $this->assertFalse($rule->check('qty', 4));
        $this->assertTrue($rule->check('qty', '')); // empty = optional
    }

    public function testMaxRule(): void {
        $rule = new Max(100);
        $this->assertTrue($rule->check('qty', 100));
        $this->assertTrue($rule->check('qty', 0));
        $this->assertFalse($rule->check('qty', 101));
        $this->assertTrue($rule->check('qty', '')); // empty = optional
    }

    public function testMinLengthRule(): void {
        $rule = new MinLength(3);
        $this->assertTrue($rule->check('name', 'abc'));
        $this->assertTrue($rule->check('name', 'abcd'));
        $this->assertFalse($rule->check('name', 'ab'));
        $this->assertTrue($rule->check('name', '')); // empty = optional
    }

    public function testMaxLengthRule(): void {
        $rule = new MaxLength(5);
        $this->assertTrue($rule->check('name', 'hello'));
        $this->assertFalse($rule->check('name', 'toolong'));
        $this->assertTrue($rule->check('name', '')); // empty = optional
    }

    public function testNumericRule(): void {
        $rule = new Numeric();
        $this->assertTrue($rule->check('qty', '42'));
        $this->assertTrue($rule->check('qty', 3.14));
        $this->assertFalse($rule->check('qty', 'abc'));
        $this->assertTrue($rule->check('qty', '')); // empty = optional
    }

    public function testHexColorRule(): void {
        $rule = new HexColor();
        $this->assertTrue($rule->check('color', '#ff0000'));
        $this->assertTrue($rule->check('color', '#AABBCC'));
        $this->assertFalse($rule->check('color', 'ff0000'));   // missing #
        $this->assertFalse($rule->check('color', '#fff'));     // short form
        $this->assertTrue($rule->check('color', ''));          // empty = optional
    }

    public function testConfirmedRule(): void {
        $rule = new Confirmed();
        $data = ['password' => 'secret', 'password_confirmation' => 'secret'];
        $this->assertTrue($rule->check('password', 'secret', $data));

        $data['password_confirmation'] = 'wrong';
        $this->assertFalse($rule->check('password', 'secret', $data));
    }

    public function testSameRule(): void {
        $rule = new Same('other');
        $data = ['field' => 'value', 'other' => 'value'];
        $this->assertTrue($rule->check('field', 'value', $data));

        $data['other'] = 'different';
        $this->assertFalse($rule->check('field', 'value', $data));
    }

    public function testRegexRule(): void {
        $rule = new Regex('/^\d{4}$/');
        $this->assertTrue($rule->check('year', '2025'));
        $this->assertFalse($rule->check('year', '25'));
        $this->assertFalse($rule->check('year', 'abcd'));
        $this->assertTrue($rule->check('year', '')); // empty = optional
    }

    // ── message() coverage ────────────────────────────────────────────────────

    public function testRuleMessagesContainFieldName(): void {
        $field = 'email';
        $this->assertStringContainsString($field, (new Required())->message($field));
        $this->assertStringContainsString($field, (new Email())->message($field));
        $this->assertStringContainsString($field, (new Min(5))->message($field));
        $this->assertStringContainsString($field, (new Max(100))->message($field));
        $this->assertStringContainsString($field, (new MinLength(3))->message($field));
        $this->assertStringContainsString($field, (new MaxLength(5))->message($field));
        $this->assertStringContainsString($field, (new Numeric())->message($field));
        $this->assertStringContainsString($field, (new HexColor())->message($field));
        $this->assertStringContainsString($field, (new Confirmed())->message($field));
        $this->assertStringContainsString($field, (new Same('other'))->message($field));
        $this->assertStringContainsString($field, (new Regex('/^\d+$/'))->message($field));
    }

    // ── Validator integration tests ───────────────────────────────────────────

    public function testValidatorPassesWhenAllRulesPass(): void {
        $validator = new Validator(
            ['name' => 'required', 'email' => 'email'],
            ['name' => 'Simon', 'email' => 'simon@skankydev.com']
        );
        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->errors());
    }

    public function testValidatorFailsAndCollectsErrors(): void {
        $validator = new Validator(
            ['name' => 'required', 'email' => 'email'],
            ['name' => '', 'email' => 'not-an-email']
        );
        $this->assertFalse($validator->validate());
        $this->assertTrue($validator->hasError('name'));
        $this->assertTrue($validator->hasError('email'));
        $this->assertNotNull($validator->error('name'));
    }

    public function testValidatorFailFastPerField(): void {
        // required + min_length:10 — only the first error (required) is kept
        $validator = new Validator(
            ['name' => 'required|min_length:10'],
            ['name' => '']
        );
        $this->assertFalse($validator->validate());
        $this->assertCount(1, $validator->errors()['name']);
    }

    public function testValidatorParameterisedRules(): void {
        $validator = new Validator(
            ['age' => 'min:18|max:99'],
            ['age' => '17']
        );
        $this->assertFalse($validator->validate());

        $validator->setData(['age' => '25']);
        $this->assertTrue($validator->validate());
    }

    public function testValidatorOptionalFieldPassesWhenEmpty(): void {
        // email rule is optional (passes on empty value)
        $validator = new Validator(
            ['email' => 'email'],
            ['email' => '']
        );
        $this->assertTrue($validator->validate());
    }

    public function testValidatorThrowsOnUnknownRule(): void {
        $this->expectException(\Exception::class);
        $validator = new Validator(['name' => 'does_not_exist'], ['name' => 'Simon']);
        $validator->validate();
    }

    public function testSetRulesReplacesRules(): void {
        $validator = new Validator(['name' => 'required'], ['name' => '']);
        $this->assertFalse($validator->validate()); // required fails on empty

        $validator->setRules(['name' => 'email']); // email passes on empty
        $this->assertTrue($validator->validate());
    }
}
