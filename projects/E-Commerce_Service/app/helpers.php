<?php
// @codeCoverageIgnoreStart
if (! function_exists('authUser')) {
  // @codeCoverageIgnoreEnd

  /**
   * @return mixed
   */
  function authUser()
  {
    return request()->attributes->get('auth_user');
  }
}
