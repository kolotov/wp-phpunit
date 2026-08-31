<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PHPUnit_Adapter_TestCase::class)]
final class PHPUnitAdapterTest extends TestCase
{
    #[Test]
    public function itRunsWordPressLifecycleHooksThroughPHPUnit13(): void
    {
        LifecycleProbeTestCase::resetEvents();

        LifecycleProbeTestCase::setUpBeforeClass();
        $probe = new LifecycleProbeTestCase('probe');
        $probe->runBare();
        LifecycleProbeTestCase::tearDownAfterClass();

        self::assertSame(
            ['before-class', 'set-up', 'pre-conditions', 'test', 'post-conditions', 'tear-down', 'after-class'],
            LifecycleProbeTestCase::events(),
        );
    }

    #[Test]
    public function subclassesCanUseNativePhpunitLifecycleMethods(): void
    {
        NativeLifecycleProbe::resetEvents();

        $probe = new NativeLifecycleProbe('probe');
        $probe->runBare();

        self::assertSame(
            ['native-set-up-before', 'wordpress-set-up', 'native-set-up-after', 'test', 'native-tear-down-before', 'wordpress-tear-down', 'native-tear-down-after'],
            NativeLifecycleProbe::events(),
        );
    }

    #[Test]
    public function hookCallbacksRemainRemovable(): void
    {
        $files = [
            'includes/abstract-testcase.php',
            'includes/testcase-ajax.php',
            'includes/testcase-rest-controller.php',
            'includes/testcase-rest-post-type-controller.php',
            'includes/wp-profiler.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(dirname(__DIR__) . '/' . $file);

            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:add|remove)_(?:filter|action)\([^;\n]*->\w+\(\.\.\.\)/',
                $source,
                $file . ' must use a stable callback representation for repeatable or removable WordPress hooks.',
            );
        }
    }

    #[Test]
    public function restControllerUsesTheSameStableCallbackForAddAndRemove(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/includes/testcase-rest-controller.php');

        self::assertIsString($source);
        self::assertStringContainsString(
            "add_filter('rest_url', [ \$this, 'filter_rest_url_for_leading_slash' ], 10, 2);",
            $source,
        );
        self::assertStringContainsString(
            "remove_filter('rest_url', [ \$this, 'filter_rest_url_for_leading_slash' ], 10);",
            $source,
        );
        self::assertStringNotContainsString("test_rest_url_for_leading_slash", $source);
    }

    #[Test]
    public function coreResetsRunPerTestRatherThanBeforeClassDatabaseReconnect(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/includes/abstract-testcase.php');

        self::assertIsString($source);

        $classSetupStart = strpos($source, 'public static function set_up_before_class()');
        $classSetupEnd   = strpos($source, 'public static function tear_down_after_class()', $classSetupStart);
        $testSetupStart  = strpos($source, 'public function set_up()', $classSetupEnd);
        $testSetupEnd    = strpos($source, 'public function wp_hash_password_options', $testSetupStart);

        self::assertIsInt($classSetupStart);
        self::assertIsInt($classSetupEnd);
        self::assertIsInt($testSetupStart);
        self::assertIsInt($testSetupEnd);

        $classSetup = substr($source, $classSetupStart, $classSetupEnd - $classSetupStart);
        $testSetup  = substr($source, $testSetupStart, $testSetupEnd - $testSetupStart);

        self::assertStringNotContainsString('reset_post_types_for_core_tests', $classSetup);
        self::assertStringNotContainsString('$wp_rewrite->flush_rules();', $classSetup);
        self::assertStringContainsString('$wpdb->db_connect();', $classSetup);
        self::assertStringContainsString('self::commit_transaction();', $classSetup);

        self::assertStringContainsString("if (\\defined('WP_RUN_CORE_TESTS') && WP_RUN_CORE_TESTS)", $testSetup);
        self::assertStringContainsString('$this->reset_post_types();', $testSetup);
        self::assertStringContainsString('$this->reset_taxonomies();', $testSetup);
        self::assertStringContainsString('$this->reset_post_statuses();', $testSetup);
    }

    #[Test]
    public function globalScopeCleanupDropsCustomizerStateBetweenTests(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/includes/abstract-testcase.php');

        self::assertIsString($source);

        $cleanupStart = strpos($source, 'public function clean_up_global_scope()');
        $cleanupEnd   = strpos($source, 'public function skipOnAutomatedBranches()', $cleanupStart);

        self::assertIsInt($cleanupStart);
        self::assertIsInt($cleanupEnd);

        $cleanup = substr($source, $cleanupStart, $cleanupEnd - $cleanupStart);

        self::assertStringContainsString("unset(\$GLOBALS['wp_customize']);", $cleanup);
    }

    #[Test]
    public function removedPhpunitCompatibilityHelpersStayAbsent(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/includes/abstract-testcase.php');

        self::assertIsString($source);

        self::assertDoesNotMatchRegularExpression(
            '/function\s+(?:setExpectedException|checkRequirements|expectPhpDeprecationMessage|expectWarning(?:Message)?|expectNotice(?:Message)?|expectError(?:Message)?)\s*\(/',
            $source,
        );
        self::assertStringNotContainsString('expected_php_error_severities', $source);
        self::assertStringNotContainsString('php_error_handler_installed', $source);
    }
}

final class NativeLifecycleProbe extends PHPUnit_Adapter_TestCase
{
    /** @var list<string> */
    private static array $events = [];

    public static function resetEvents(): void
    {
        self::$events = [];
    }

    /** @return list<string> */
    public static function events(): array
    {
        return self::$events;
    }

    protected function setUp(): void
    {
        self::$events[] = 'native-set-up-before';
        parent::setUp();
        self::$events[] = 'native-set-up-after';
    }

    protected function tearDown(): void
    {
        self::$events[] = 'native-tear-down-before';
        parent::tearDown();
        self::$events[] = 'native-tear-down-after';
    }

    public function set_up()
    {
        self::$events[] = 'wordpress-set-up';
    }

    public function tear_down()
    {
        self::$events[] = 'wordpress-tear-down';
    }

    public function probe(): void
    {
        self::$events[] = 'test';
        self::assertTrue(true);
    }
}

final class LifecycleProbeTestCase extends PHPUnit_Adapter_TestCase
{
    /** @var list<string> */
    private static array $events = [];

    public static function resetEvents(): void
    {
        self::$events = [];
    }

    /** @return list<string> */
    public static function events(): array
    {
        return self::$events;
    }

    public static function set_up_before_class()
    {
        self::$events[] = 'before-class';
    }

    public static function tear_down_after_class()
    {
        self::$events[] = 'after-class';
    }

    public function set_up()
    {
        self::$events[] = 'set-up';
    }

    public function tear_down()
    {
        self::$events[] = 'tear-down';
    }

    protected function assert_pre_conditions()
    {
        self::$events[] = 'pre-conditions';
    }

    protected function assert_post_conditions()
    {
        self::$events[] = 'post-conditions';
    }

    public function probe(): void
    {
        self::$events[] = 'test';
        self::assertTrue(true);
    }
}
