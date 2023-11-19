<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Webrium\FormValidation;

final class FormValidationTest extends TestCase
{

    public function testCheckEmailValidation(){

        $array = [
            'email'=>'fdsfdsajklfas',
        ];

        $form = new FormValidation($array);

        $form->field('email')->email();

        $this->assertFalse($form->isValid());



        $array = [
            'email'=>'test@gmail.com',
        ];

        $form = new FormValidation($array);

        $form->field('email')->email();

        $this->assertTrue($form->isValid());
    }

    public function testCheckUrlValidation(){

        $array = [
            'site_url'=>'https://google.com',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->url();

        $this->assertTrue($form->isValid());


        $array = [
            'site_url'=>'httpgoogle.com',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->url();

        $this->assertFalse($form->isValid());
    }

    public function testCheckDomainValidation(){

        $array = [
            'site_url'=>'https://google.com',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->domain();

        $this->assertTrue($form->isValid());


        $array = [
            'site_url'=>'https://google',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->domain();

        $this->assertFalse($form->isValid());
    }
}