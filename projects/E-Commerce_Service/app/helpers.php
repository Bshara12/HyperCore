<?php
  // @codeCoverageIgnoreStart
if (! function_exists('authUser')) {
  // @codeCoverageIgnoreEnd
  function authUser()
  {
    return request()->attributes->get('auth_user');
  }
}
