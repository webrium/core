<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Webrium\FormValidation;

final class FormValidationTest extends TestCase
{

    public function testCheckEmailField(){

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

    public function testCheckUrlField(){

        $array = [
            'site_url'=>'https://google.com/',
        ];

        $form = new FormValidation($array);

        $form->field('site_url')->url();

        $this->assertTrue($form->isValid());
    }
}