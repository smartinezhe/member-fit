<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case and database
|--------------------------------------------------------------------------
| Feature tests extend the Laravel TestCase and reset the database on every
| test. phpunit.xml points them to the dedicated `member_fit_testing` MySQL
| database (DP-12), so the development database is never touched.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
