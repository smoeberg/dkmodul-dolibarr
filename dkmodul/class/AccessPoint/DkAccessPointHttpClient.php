<?php

interface DkAccessPointHttpClient
{
    public function request($method, $path, array $headers = array(), $body = null, array $query = array());
}
