<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Webrium\Validator;

class ValidatorTest extends TestCase
{
    public function testRequiredFailsWhenFieldMissing(): void
    {
        $validator = new Validator(['name' => '']);
        $validator->field('name')->required();

        $this->assertFalse($validator->validate());
        $this->assertEquals('name', $validator->getFirstError()['field']);
        $this->assertSame(
            'The name field is required.',
            $validator->getFirstErrorMessage()
        );
    }

    public function testRequiredPassesWhenFieldPresent(): void
    {
        $validator = new Validator(['name' => 'John']);
        $validator->field('name')->required();

        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->getErrors());
    }

    public function testCustomMessageOverridesDefaultMessage(): void
    {
        $validator = new Validator(['name' => '']);
        $validator->field('name')->required('Please provide your name.');

        $this->assertFalse($validator->validate());
        $this->assertSame('Please provide your name.', $validator->getFirstErrorMessage());
    }

    public function testLabelIsUsedInGeneratedMessage(): void
    {
        $validator = new Validator(['email_address' => '']);
        $validator->field('email_address', 'Email')->required();

        $this->assertFalse($validator->validate());
        $this->assertSame('The Email field is required.', $validator->getFirstErrorMessage());
    }

    public function testStringRule(): void
    {
        $validator = new Validator(['name' => 123]);
        $validator->field('name')->string();

        $this->assertFalse($validator->validate());
        $this->assertSame('The name must be a string.', $validator->getFirstErrorMessage());
    }

    public function testAlphaRule(): void
    {
        $valid = new Validator(['name' => 'Webrium']);
        $valid->field('name')->alpha();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['name' => 'Webrium123']);
        $invalid->field('name')->alpha();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The name must only contain letters.', $invalid->getFirstErrorMessage());
    }

    public function testAlphaNumRule(): void
    {
        $valid = new Validator(['username' => 'user123']);
        $valid->field('username')->alphaNum();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['username' => 'user-123']);
        $invalid->field('username')->alphaNum();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The username must only contain letters and numbers.', $invalid->getFirstErrorMessage());
    }

    public function testNumericRule(): void
    {
        $valid = new Validator(['age' => '25']);
        $valid->field('age')->numeric();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['age' => 'twenty-five']);
        $invalid->field('age')->numeric();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The age must be a number.', $invalid->getFirstErrorMessage());
    }

    public function testIntegerRule(): void
    {
        $valid = new Validator(['count' => '10']);
        $valid->field('count')->integer();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['count' => '10.5']);
        $invalid->field('count')->integer();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The count must be an integer.', $invalid->getFirstErrorMessage());
    }

    public function testBooleanRule(): void
    {
        $valid = new Validator(['active' => true]);
        $valid->field('active')->boolean();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['active' => 'yes']);
        $invalid->field('active')->boolean();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The active field must be true or false.', $invalid->getFirstErrorMessage());
    }

    public function testDigitsRule(): void
    {
        $valid = new Validator(['pin' => '1234']);
        $valid->field('pin')->digits(4);
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['pin' => '123']);
        $invalid->field('pin')->digits(4);
        $this->assertFalse($invalid->validate());
        $this->assertSame('The pin must be 4 digits.', $invalid->getFirstErrorMessage());
    }

    public function testDigitsBetweenRule(): void
    {
        $valid = new Validator(['code' => '12345']);
        $valid->field('code')->digitsBetween(3, 6);
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['code' => '12']);
        $invalid->field('code')->digitsBetween(3, 6);
        $this->assertFalse($invalid->validate());
        $this->assertSame('The code must be between 3 and 6 digits.', $invalid->getFirstErrorMessage());
    }

    public function testDifferentRule(): void
    {
        $valid = new Validator(['old_password' => 'abc', 'new_password' => 'xyz']);
        $valid->field('new_password')->different('old_password');
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['old_password' => 'abc', 'new_password' => 'abc']);
        $invalid->field('new_password')->different('old_password');
        $this->assertFalse($invalid->validate());
        $this->assertSame(
            'The new_password and old_password must be different.',
            $invalid->getFirstErrorMessage()
        );
    }

    public function testConfirmedRule(): void
    {
        $valid = new Validator(['password' => 'secret', 'password_confirmation' => 'secret']);
        $valid->field('password')->confirmed('password_confirmation');
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['password' => 'secret', 'password_confirmation' => 'other']);
        $invalid->field('password')->confirmed('password_confirmation');
        $this->assertFalse($invalid->validate());
        $this->assertSame('The password confirmation does not match.', $invalid->getFirstErrorMessage());
    }

    public function testMinRuleForString(): void
    {
        $valid = new Validator(['username' => 'johndoe']);
        $valid->field('username')->min(3);
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['username' => 'jo']);
        $invalid->field('username')->min(3);
        $this->assertFalse($invalid->validate());
        $this->assertSame('The username must be at least 3 characters.', $invalid->getFirstErrorMessage());
    }

    public function testMinRuleForNumeric(): void
    {
        $invalid = new Validator(['age' => 15]);
        $invalid->field('age')->min(18);

        $this->assertFalse($invalid->validate());
        $this->assertSame('The age must be at least 18.', $invalid->getFirstErrorMessage());
    }

    public function testMinRuleForArray(): void
    {
        $invalid = new Validator(['tags' => ['one']]);
        $invalid->field('tags')->min(2);

        $this->assertFalse($invalid->validate());
        $this->assertSame('The tags must have at least 2 items.', $invalid->getFirstErrorMessage());
    }

    public function testMaxRuleForString(): void
    {
        $invalid = new Validator(['username' => 'thisusernameistoolong']);
        $invalid->field('username')->max(10);

        $this->assertFalse($invalid->validate());
        $this->assertSame('The username must not be greater than 10 characters.', $invalid->getFirstErrorMessage());
    }

    public function testMaxRuleForNumeric(): void
    {
        $invalid = new Validator(['age' => 150]);
        $invalid->field('age')->max(120);

        $this->assertFalse($invalid->validate());
        $this->assertSame('The age must not be greater than 120.', $invalid->getFirstErrorMessage());
    }

    public function testMaxRuleForArray(): void
    {
        $invalid = new Validator(['tags' => ['a', 'b', 'c']]);
        $invalid->field('tags')->max(2);

        $this->assertFalse($invalid->validate());
        $this->assertSame('The tags must not have more than 2 items.', $invalid->getFirstErrorMessage());
    }

    public function testBetweenRuleForString(): void
    {
        $valid = new Validator(['username' => 'john']);
        $valid->field('username')->between(3, 10);
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['username' => 'jo']);
        $invalid->field('username')->between(3, 10);
        $this->assertFalse($invalid->validate());
        $this->assertSame('The username must be between 3 and 10 characters.', $invalid->getFirstErrorMessage());
    }

    public function testBetweenRuleForNumeric(): void
    {
        $valid = new Validator(['age' => 25]);
        $valid->field('age')->between(18, 65);
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['age' => 17]);
        $invalid->field('age')->between(18, 65);
        $this->assertFalse($invalid->validate());
        $this->assertSame('The age must be between 18 and 65.', $invalid->getFirstErrorMessage());
    }

    public function testBetweenRuleForArray(): void
    {
        $invalid = new Validator(['tags' => ['a']]);
        $invalid->field('tags')->between(2, 5);

        $this->assertFalse($invalid->validate());
        $this->assertSame('The tags must have between 2 and 5 items.', $invalid->getFirstErrorMessage());
    }

    public function testEmailRule(): void
    {
        $valid = new Validator(['email' => 'user@example.com']);
        $valid->field('email')->email();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['email' => 'not-an-email']);
        $invalid->field('email')->email();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The email must be a valid email address.', $invalid->getFirstErrorMessage());
    }

    public function testPhoneRule(): void
    {
        $valid = new Validator(['phone' => '+1234567890']);
        $valid->field('phone')->phone();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['phone' => 'abc']);
        $invalid->field('phone')->phone();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The phone must be a valid phone number.', $invalid->getFirstErrorMessage());
    }

    public function testUrlRule(): void
    {
        $valid = new Validator(['website' => 'https://example.com']);
        $valid->field('website')->url();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['website' => 'not a url']);
        $invalid->field('website')->url();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The website format is invalid.', $invalid->getFirstErrorMessage());
    }

    public function testDomainRule(): void
    {
        $valid = new Validator(['site' => 'example.com']);
        $valid->field('site')->domain();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['site' => 'not a domain!']);
        $invalid->field('site')->domain();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The site must be a valid domain.', $invalid->getFirstErrorMessage());
    }

    public function testMacRule(): void
    {
        $valid = new Validator(['mac' => '00:1B:44:11:3A:B7']);
        $valid->field('mac')->mac();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['mac' => 'not-a-mac']);
        $invalid->field('mac')->mac();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The mac must be a valid MAC address.', $invalid->getFirstErrorMessage());
    }

    public function testIpRule(): void
    {
        $valid = new Validator(['ip' => '192.168.1.1']);
        $valid->field('ip')->ip();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['ip' => '999.999.999.999']);
        $invalid->field('ip')->ip();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The ip must be a valid IP address.', $invalid->getFirstErrorMessage());
    }

    public function testArrayRule(): void
    {
        $valid = new Validator(['items' => ['a', 'b']]);
        $valid->field('items')->array();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['items' => 'not-an-array']);
        $invalid->field('items')->array();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The items must be an array.', $invalid->getFirstErrorMessage());
    }

    public function testObjectRule(): void
    {
        $valid = new Validator(['payload' => new \stdClass()]);
        $valid->field('payload')->object();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['payload' => 'not-an-object']);
        $invalid->field('payload')->object();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The payload must be an object.', $invalid->getFirstErrorMessage());
    }

    public function testRegexRule(): void
    {
        $valid = new Validator(['slug' => 'my-post-title']);
        $valid->field('slug')->regex('/^[a-z0-9\-]+$/');
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['slug' => 'My Post Title']);
        $invalid->field('slug')->regex('/^[a-z0-9\-]+$/');
        $this->assertFalse($invalid->validate());
        $this->assertSame('The slug format is invalid.', $invalid->getFirstErrorMessage());
    }

    public function testRegexRuleRejectsDangerousPatterns(): void
    {
        $validator = new Validator(['value' => 'test']);
        $validator->field('value');

        $this->expectException(\Exception::class);
        $validator->regex('/(a+)+{2}/');
    }

    public function testInRule(): void
    {
        $valid = new Validator(['role' => 'admin']);
        $valid->field('role')->in(['admin', 'editor', 'viewer']);
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['role' => 'superuser']);
        $invalid->field('role')->in(['admin', 'editor', 'viewer']);
        $this->assertFalse($invalid->validate());
        $this->assertSame('The selected role is invalid.', $invalid->getFirstErrorMessage());
    }

    public function testNotInRule(): void
    {
        $valid = new Validator(['username' => 'john']);
        $valid->field('username')->notIn(['admin', 'root']);
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['username' => 'admin']);
        $invalid->field('username')->notIn(['admin', 'root']);
        $this->assertFalse($invalid->validate());
        $this->assertSame('The selected username is invalid.', $invalid->getFirstErrorMessage());
    }

    public function testJsonRule(): void
    {
        $valid = new Validator(['payload' => '{"key":"value"}']);
        $valid->field('payload')->json();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['payload' => '{invalid}']);
        $invalid->field('payload')->json();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The payload must be a valid JSON string.', $invalid->getFirstErrorMessage());
    }

    public function testDateRuleWithDefaultFormat(): void
    {
        $valid = new Validator(['birthday' => '2024-01-15']);
        $valid->field('birthday')->date();
        $this->assertTrue($valid->validate());

        $invalid = new Validator(['birthday' => '15/01/2024']);
        $invalid->field('birthday')->date();
        $this->assertFalse($invalid->validate());
        $this->assertSame('The birthday is not a valid date.', $invalid->getFirstErrorMessage());
    }

    public function testDateRuleWithCustomFormat(): void
    {
        $valid = new Validator(['birthday' => '15/01/2024']);
        $valid->field('birthday')->date('d/m/Y');
        $this->assertTrue($valid->validate());
    }

    public function testNullableSkipsValidationWhenEmpty(): void
    {
        $validator = new Validator(['nickname' => '']);
        $validator->field('nickname')->nullable()->min(3);

        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->getErrors());
    }

    public function testNullableStillValidatesWhenValuePresent(): void
    {
        $validator = new Validator(['nickname' => 'ab']);
        $validator->field('nickname')->nullable()->min(3);

        $this->assertFalse($validator->validate());
        $this->assertSame('The nickname must be at least 3 characters.', $validator->getFirstErrorMessage());
    }

    public function testSometimesSkipsValidationWhenFieldAbsent(): void
    {
        $validator = new Validator([]);
        $validator->field('promo_code')->sometimes()->string();

        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->getErrors());
    }

    public function testSometimesValidatesWhenFieldPresent(): void
    {
        $validator = new Validator(['promo_code' => 123]);
        $validator->field('promo_code')->sometimes()->string();

        $this->assertFalse($validator->validate());
        $this->assertSame('The promo_code must be a string.', $validator->getFirstErrorMessage());
    }

    public function testNonRequiredRulesAreSkippedWhenFieldHasNoValueAndIsNotNullable(): void
    {
        $validator = new Validator(['name' => '']);
        $validator->field('name')->string()->min(3);

        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->getErrors());
    }

    public function testStopsAtFirstFailingRulePerField(): void
    {
        $validator = new Validator(['age' => 'not-a-number']);
        $validator->field('age')->numeric()->min(18);

        $this->assertFalse($validator->validate());
        $this->assertCount(1, $validator->getErrors());
        $this->assertSame('The age must be a number.', $validator->getFirstErrorMessage());
    }

    public function testMultipleFieldsAreAllValidated(): void
    {
        $validator = new Validator([
            'name' => '',
            'email' => 'invalid-email',
            'age' => 25,
        ]);

        $validator->field('name')->required();
        $validator->field('email')->email();
        $validator->field('age')->integer();

        $this->assertFalse($validator->validate());

        $errors = $validator->getErrors();
        $fields = array_column($errors, 'field');

        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertNotContains('age', $fields);
    }

    public function testPassesAndFailsHelpers(): void
    {
        $valid = new Validator(['name' => 'John']);
        $valid->field('name')->required();

        $this->assertTrue($valid->passes());
        $this->assertFalse($valid->fails());

        $invalid = new Validator(['name' => '']);
        $invalid->field('name')->required();

        $this->assertFalse($invalid->passes());
        $this->assertTrue($invalid->fails());
    }

    public function testFailsAutomaticallyRunsValidationWhenNotYetExecuted(): void
    {
        $validator = new Validator(['name' => '']);
        $validator->field('name')->required();

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->getErrors());
    }

    public function testIsValidAliasMatchesValidate(): void
    {
        $validator = new Validator(['name' => 'John']);
        $validator->field('name')->required();

        $this->assertTrue($validator->isValid());
    }

    public function testGetFirstErrorReturnsNullWhenNoErrors(): void
    {
        $validator = new Validator(['name' => 'John']);
        $validator->field('name')->required();
        $validator->validate();

        $this->assertNull($validator->getFirstError());
        $this->assertNull($validator->getFirstErrorMessage());
    }

    public function testValueIsTrimmedBeforeValidation(): void
    {
        $validator = new Validator(['code' => '  1234  ']);
        $validator->field('code')->digits(4);

        $this->assertTrue($validator->validate());
    }
}