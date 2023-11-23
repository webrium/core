<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Webrium\FormValidation;

final class FormValidationTest extends TestCase
{

    public function testCheckEmailValidation()
    {

        $array = [
            'email' => 'fdsfdsajklfas',
        ];

        $form = new FormValidation($array);

        $form->field('email')->email();

        $this->assertFalse($form->isValid());



        $array = [
            'email' => 'test@gmail.com',
        ];

        $form = new FormValidation($array);

        $form->field('email')->email();

        $this->assertTrue($form->isValid());
    }

    public function testCheckUrlValidation()
    {

        $array = [
            'site_url' => 'https://google.com',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->url();

        $this->assertTrue($form->isValid());


        $array = [
            'site_url' => 'httpgoogle.com',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->url();

        $this->assertFalse($form->isValid());
    }

    public function testCheckDomainValidation()
    {

        $array = [
            'site_url' => 'https://google.com',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->domain();

        $this->assertTrue($form->isValid());


        $array = [
            'site_url' => 'https://google',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->domain();

        $this->assertFalse($form->isValid());
    }

    public function testCheckMacValidation()
    {

        $array = [
            'mac' => '00-B0-D0-63-C2-26',
        ];

        $form = new FormValidation($array);

        $form->field('mac')->mac();

        $this->assertTrue($form->isValid());


        $array = [
            'mac' => '00-B0-D0-63-C2-2',
        ];

        $form = new FormValidation($array);

        $form->field('mac')->mac();

        $this->assertFalse($form->isValid());
    }

    public function testCheckIpValidation()
    {

        $array = [
            'ip' => '8.8.8.8',
        ];

        $form = new FormValidation($array);

        $form->field('ip')->ip();

        $this->assertTrue($form->isValid());


        $array = [
            'ip' => '8.8.8.266',
        ];

        $form = new FormValidation($array);

        $form->field('ip')->ip();

        $this->assertFalse($form->isValid());
    }

    public function testCheckMinAndMaxStringLength()
    {
        $array = [
            'name' => 'BE',
        ];

        $form = new FormValidation($array);

        $form->field('name')->min(3);

        $is_valid = $form->isValid();
        $message = $form->getFirstError();

        $this->assertFalse($is_valid, $message['message'] ?? '');


        $array = [
            'name' => 'BEN',
            'mobile' => '09999999990',

        ];

        $form = new FormValidation($array);

        $form->field('name')->min(3)->max(3);
        $form->field('mobile')->min(11)->max(11);

        $is_valid = $form->isValid();
        $message = $form->getFirstError();

        $this->assertTrue($is_valid, $message['message'] ?? '');


    }


    public function testCheckMinAndMaxIntegerLength()
    {


        $array = [
            'age' => 15,
        ];

        $form = new FormValidation($array);

        $form->field('age')->min(18);

        $is_valid = $form->isValid();

        $this->assertFalse($is_valid);



        $array = [
            'age' => 18,
        ];

        $form = new FormValidation($array);

        $form->field('age')->min(18)->max(30);

        $is_valid = $form->isValid();

        $this->assertTrue($is_valid);



        $array = [
            'age' => 19,
        ];

        $form = new FormValidation($array);

        $form->field('age')->max(18);

        $is_valid = $form->isValid();

        $this->assertFalse($is_valid);
    }

    public function testCheckMinAndMaxArrayLength()
    {
        $array = [
            'category' => ['cat', 'car'],
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('category')->min(3)->isValid();

        $this->assertFalse($is_valid);

        $array = [
            'category' => ['cat', 'car'],
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('category')->min(2)->max(5)->isValid();

        $this->assertTrue($is_valid);

        $array = [
            'category' => ['cat', 'car', 'dog'],
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('category')->min(1)->max(2)->isValid();

        $this->assertFalse($is_valid);
    }



    public function testCheckIntegerAndNumericType()
    {
        $array = [
            'age' => '19',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('age')->integer()->isValid();

        $this->assertFalse($is_valid);


        $array = [
            'age' => 19,
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('age')->integer()->isValid();

        $this->assertTrue($is_valid);


        $array = [
            'age' => 's44',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('age')->numeric()->isValid();

        $this->assertFalse($is_valid);


        $array = [
            'age' => '44',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('age')->numeric()->isValid();

        $this->assertTrue($is_valid);
    }

    public function testCheckStringValidation()
    {
        $array = [
            'name' => 'BEN',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('name')->string()->isValid();

        $this->assertTrue($is_valid);


        $array = [
            'name' => 333,
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('name')->string()->isValid();

        $this->assertFalse($is_valid);


        $array = [
            'name' => '333',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('name')->string()->isValid();

        $this->assertTrue($is_valid);
    }


    public function testCheckArrayValidation(){
        $array = [
            'category' => '333',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('category')->array()->isValid();

        $this->assertFalse($is_valid);


        $array = [
            'category' => ['cat', 'dog'],
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('category')->array()->isValid();

        $this->assertTrue($is_valid);
    }

    public function testCheckOnjectValidation(){
        $array = [
            'user' => '333',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('user')->object()->isValid();

        $this->assertFalse($is_valid);

        $std = new stdClass;
        $std->name = 'BEN';

        $array = [
            'user' => $std,
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('user')->object()->isValid();

        $this->assertTrue($is_valid);
    }


    public function testCheckDigitsValidation()
    {
        $array = [
            'mobile' => '09999999999',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('mobile')->digits(11)->isValid();

        $this->assertTrue($is_valid);


        $array = [
            'mobile' => '0999999999',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('mobile')->digits(11)->isValid();

        $this->assertFalse($is_valid);
    }


    public function testCheckDigitsBetweenValidation()
    {
        $array = [
            'mobile' => '0999999910',
            'mobile2' => '09999999911'
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('mobile')->digitsBetween(10, 11)->isValid();
        $is_valid = $form->field('mobile2')->digitsBetween(10, 11)->isValid();

        $this->assertTrue($is_valid);


        $array = [
            'mobile' => '0999999999',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('mobile')->digitsBetween(11, 12)->isValid();

        $this->assertFalse($is_valid);

        $array = [
            'mobile' => '0999999999000',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('mobile')->digitsBetween(11, 12)->isValid();

        $this->assertFalse($is_valid);
    }

    
    public function testCheckDifferentValidation(){
        $array = [
            'father_mobile' => '0999999999000',
            'mother_mobile' => '0999999999001',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('father_mobile')->different('mother_mobile')->isValid();

        $this->assertTrue($is_valid);

        $array = [
            'father_mobile' => '0999999999000',
            'mother_mobile' => '0999999999000',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('father_mobile')->different('mother_mobile')->isValid();

        $this->assertFalse($is_valid);
    }


    public function testCheckConfirmedValidation(){
        $array = [
            'password' => '1234567',
            'confirm_password' => '1234567',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('password')->confirmed('confirm_password')->isValid();

        $this->assertTrue($is_valid);

        $array = [
            'password' => '1234567',
            'confirm_password' => '123456',
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('password')->confirmed('confirm_password')->isValid();

        $this->assertFalse($is_valid);
    }


    public function testCheckRequiredValidation(){
        $array = [
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('username')->required()->isValid();

        $this->assertFalse($is_valid);

        $array = [
            'username'=>''
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('username')->required()->isValid();

        $this->assertFalse($is_valid);


        $array = [
            'username'=>'dsfsdfs'
        ];

        $form = new FormValidation($array);

        $is_valid = $form->field('username')->required()->isValid();

        $this->assertTrue($is_valid);
    }


    public function testCheckIgnoreConditionsWhenNoValueIsPassed(){
        $array = [
        ];

        $form = new FormValidation($array);
        $form->field('username')->digits(10);
        $is_valid = $form->isValid();
        $this->assertTrue($is_valid, $form->getFirstError()['message']??'');
    }


    public function testCheckErrorMessages(){

        $array = [
            'name'=>'BEN',
            'email'=>'benkhaliferoton.me',
            'username'=>'benkhalife',
            'password'=> '1234567',
            'confirm_password'=> '12345678',
            // 'age'=>18,
        ];
        $form = new FormValidation($array);
        $form->field('email')->required()->email();
        $form->field('age')->required();

        
        $is_valid = $form->isValid();

        $this->assertEquals('The email must be a valid email address.', $form->getFirstError()['message']??'');


        $array = [
            'name'=>'BEN',
            'email'=>'benkhalif@proton.me',
            'username'=>'benkhalife',
            'password'=> '1234567',
            'confirm_password'=> '12345678',
            'age'=>'18',
        ];
        $form = new FormValidation($array);
        $form->field('email')->required()->email();
        $form->field('age')->required()->integer();

        
        $is_valid = $form->isValid();

        $this->assertEquals('The age must be an integer.', $form->getFirstError()['message']??'');
    }


    public function testSimulationOfRealValidationMode(){
        $array = [
            'name'=>'BEN',
            'email'=>'benkhalife@proton.me',
            'username'=>'benkhalife',
            'password'=> '12345678',
            'confirm_password'=> '12345678',
            'age'=>18,
        ];

        $form = new FormValidation($array);
        $form->field('name')->required()->min(2)->max(25);
        $form->field('email')->required()->email();
        $form->field('password')->required()->confirmed('confirm_password')->min(6);
        $form->field('age')->required()->integer()->min(12)->max(99);

        $is_valid = $form->isValid();
        $this->assertTrue($is_valid, $form->getFirstError()['message']??'');



        $array = [
            'name'=>'BEN',
            'email'=>'benkhalife@proton.me',
            'username'=>'benkhalife',
            'password'=> '12345678',
            'confirm_password'=> '12345678',
            // 'age'=>18,
        ];

        $form = new FormValidation($array);
        $form->field('name')->required()->min(2)->max(25);
        $form->field('username')->required()->min(2)->max(25);
        $form->field('email')->required()->email();
        $form->field('password')->required()->confirmed('confirm_password')->min(6);
        $form->field('age')->integer()->min(12)->max(99);
        
        $is_valid = $form->isValid();
        $this->assertTrue($is_valid, $form->getFirstError()['message']??'');



        $array = [
            'name'=>'BEN',
            'email'=>'benkhaliferoton.me',
            'username'=>'benkhalife',
            'password'=> '1234567',
            'confirm_password'=> '12345678',
            // 'age'=>18,
        ];

        $form = new FormValidation($array);
        $form->field('name')->required()->min(2)->max(25);
        $form->field('username')->required()->min(2)->max(25);
        $form->field('email')->required()->email();
        $form->field('password')->required()->confirmed('confirm_password')->min(6);
        $form->field('age')->integer()->min(12)->max(99);
        
        $is_valid = $form->isValid();
        $this->assertFalse($is_valid, $form->getFirstError()['message']??'');
    }


}
