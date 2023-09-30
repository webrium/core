<?php

namespace Webrium;

use Webrium\FromValidation;

class Request
{
    public static function validate()
    {
        return new FormValidation();
    }

}
