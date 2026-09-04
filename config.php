<?php

/*
 * Static, non-.env defaults for PayPal settings (plugin format fallback).
 *
 * From version 3.1 the runtime source of truth is admin_config (per store, secrets
 * encrypted at rest) — see AppConfig / function.php. This file no longer reads env():
 * .env is consulted only once by AppConfig::update() to migrate a legacy install into
 * the database, never at runtime. These values are the last-resort defaults returned by
 * paypalexpress_config() when a key has no database row.
 *
 * return_url / cancel_url are NOT listed here: they are computed per request from the
 * plugin route (paypalexpress_return_url() / paypalexpress_cancel_url()) so they always
 * match the store's current domain.
 */
return [
    'sandbox' => false,
    'client_id_sandbox' => '',
    'client_secret_sandbox' => '',
    'client_id_live' => '',
    'client_secret_live' => '',
    'webhook_id' => '',
];
