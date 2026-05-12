<?php

if (! function_exists('authUser')) {
    function authUser()
    {
        return request()->attributes->get('auth_user');
    }
}
