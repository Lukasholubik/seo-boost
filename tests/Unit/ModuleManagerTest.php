<?php

namespace SeoBoost\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once SEOB_PLUGIN_DIR . 'includes/ModuleManager.php';

final class ModuleManagerTest extends TestCase {

	public function test_module_dependencies_reference_existing_modules(): void {
		$modules = \SEOB_Module_Manager::MODULES;

		foreach ( $modules as $id => $module ) {
			foreach ( $module['depends_on'] as $dependency ) {
				$this->assertArrayHasKey(
					$dependency,
					$modules,
					sprintf( 'Module "%s" depends on unknown module "%s".', $id, $dependency )
				);
			}
		}
	}

	public function test_every_module_has_required_keys(): void {
		foreach ( \SEOB_Module_Manager::MODULES as $id => $module ) {
			foreach ( [ 'label', 'description', 'classes', 'depends_on' ] as $key ) {
				$this->assertArrayHasKey( $key, $module, sprintf( 'Module "%s" missing key "%s".', $id, $key ) );
			}

			$this->assertIsArray( $module['classes'], sprintf( 'Module "%s": "classes" must be an array.', $id ) );
			$this->assertIsArray( $module['depends_on'], sprintf( 'Module "%s": "depends_on" must be an array.', $id ) );
		}
	}

	public function test_is_rank_math_active_returns_false_when_class_missing(): void {
		$this->assertFalse( \SEOB_Module_Manager::is_rank_math_active() );
	}
}
