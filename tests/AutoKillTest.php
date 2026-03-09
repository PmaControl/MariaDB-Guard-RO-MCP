<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AutoKillTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['MAX_SELECT_TIME_S'] = '5';
        $_ENV['AUTO_KILL_DB_SELECT'] = '0';
        $_ENV['DB_USER'] = 'cline';
        $_ENV['DB_NAME'] = 'sakila';
    }

    public function testEnabledForQueryRequiresExplicitOptIn(): void
    {
        $this->assertFalse(AutoKill::enabledForQuery('db_select', 'SELECT SLEEP(7)'));

        $_ENV['AUTO_KILL_DB_SELECT'] = '1';
        $this->assertTrue(AutoKill::enabledForQuery('db_select', 'SELECT SLEEP(7)'));
    }

    public function testEnabledForQueryRejectsNonDbSelectTools(): void
    {
        $_ENV['AUTO_KILL_DB_SELECT'] = '1';

        $this->assertFalse(AutoKill::enabledForQuery('db_processlist', 'SELECT 1'));
        $this->assertFalse(AutoKill::enabledForQuery('db_select', 'SHOW PROCESSLIST'));
    }

    public function testKillableProcesslistRowRequiresExactConnectionAndActiveQuery(): void
    {
        $row = [
            'ID' => 42,
            'USER' => 'cline',
            'DB' => 'sakila',
            'COMMAND' => 'Query',
            'INFO' => 'SELECT SLEEP(7)',
        ];

        $this->assertTrue(AutoKill::isKillableProcesslistRow($row, 42, 'cline', 'sakila'));
        $this->assertFalse(AutoKill::isKillableProcesslistRow($row, 43, 'cline', 'sakila'));
    }

    public function testKillableProcesslistRowRejectsSleepAndOwnershipMismatch(): void
    {
        $sleepRow = [
            'ID' => 42,
            'USER' => 'cline',
            'DB' => 'sakila',
            'COMMAND' => 'Sleep',
            'INFO' => '',
        ];
        $otherUserRow = [
            'ID' => 42,
            'USER' => 'other',
            'DB' => 'sakila',
            'COMMAND' => 'Query',
            'INFO' => 'SELECT SLEEP(7)',
        ];
        $otherDbRow = [
            'ID' => 42,
            'USER' => 'cline',
            'DB' => 'mysql',
            'COMMAND' => 'Query',
            'INFO' => 'SELECT SLEEP(7)',
        ];

        $this->assertFalse(AutoKill::isKillableProcesslistRow($sleepRow, 42, 'cline', 'sakila'));
        $this->assertFalse(AutoKill::isKillableProcesslistRow($otherUserRow, 42, 'cline', 'sakila'));
        $this->assertFalse(AutoKill::isKillableProcesslistRow($otherDbRow, 42, 'cline', 'sakila'));
    }
}
