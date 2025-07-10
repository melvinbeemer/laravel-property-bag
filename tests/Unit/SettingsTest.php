<?php

namespace LaravelPropertyBag\tests\Unit;

use PHPUnit\Framework\Attributes\Test;

use Illuminate\Support\Collection;
use LaravelPropertyBag\tests\TestCase;
use LaravelPropertyBag\Settings\Settings;
use LaravelPropertyBag\tests\Classes\User;
use LaravelPropertyBag\Settings\ResourceConfig;
use LaravelPropertyBag\Exceptions\InvalidSettingsValue;

class SettingsTest extends TestCase
{
    /**
     */
    #[Test]
    public function a_resource_can_access_the_settings_object()
    {
        $this->assertInstanceOf(Settings::class, $this->user->settings());
    }

    /**
     */
    #[Test]
    public function exception_is_thrown_when_config_file_not_found()
    {
        $this->expectException(\LaravelPropertyBag\Exceptions\ResourceNotFound::class);
        $this->expectExceptionMessage('Class App\Settings\AdminSettings not found.');
        
        $this->makeAdmin()->settings();
    }

    /**
     */
    #[Test]
    public function settings_class_has_registered_settings()
    {
        $registered = $this->user->settings()->getRegistered();

        $this->assertInstanceOf(Collection::class, $registered);

        $this->assertCount(17, $registered->flatten());
    }

    /**
     */
    #[Test]
    public function resource_config_can_access_orignal_model()
    {
        $resourceConfig = $this->user->settings()->getResourceConfig();

        $this->assertInstanceOf(ResourceConfig::class, $resourceConfig);

        $resource = $resourceConfig->getResource();

        $this->assertInstanceOf(User::class, $resource);

        $this->assertEquals($this->user->id, $resource->id);
    }

    /**
     */
    #[Test]
    public function settings_class_can_check_for_registered_settings()
    {
        $group = $this->makeGroup();

        $settings = $group->settings();

        $this->assertTrue($settings->isRegistered('test_settings1'));
    }

    /**
     */
    #[Test]
    public function a_valid_setting_key_value_pair_passes_validation()
    {
        $result = $this->user->settings()->isValid('test_settings1', 'bananas');

        $this->assertTrue($result);
    }

    /**
     */
    #[Test]
    public function an_invalid_setting_key_fails_validation()
    {
        $result = $this->user->settings()->isValid('fake', true);

        $this->assertFalse($result);
    }

    /**
     */
    #[Test]
    public function an_invalid_setting_value_fails_validation()
    {
        $result = $this->user->settings()->isValid('test_settings2', 'ok');

        $this->assertFalse($result);
    }

    /**
     */
    #[Test]
    public function a_default_value_can_de_detected()
    {
        $result = $this->user->settings()->isDefault('test_settings3', false);

        $this->assertTrue($result);
    }

    /**
     */
    #[Test]
    public function a_non_default_value_can_de_detected()
    {
        $result = $this->user->settings()->isDefault('test_settings3', true);

        $this->assertFalse($result);
    }

    /**
     */
    #[Test]
    public function a_resource_can_get_the_default_value()
    {
        $default = $this->user->settings()->getDefault('test_settings1');

        $this->assertEquals('monkey', $default);
    }

    /**
     */
    #[Test]
    public function a_resource_can_get_all_the_default_values()
    {
        $defaults = $this->user->settings()->allDefaults();

        $this->assertEquals([
            'test_settings1' => 'monkey',
            'test_settings2' => true,
            'test_settings3' => false,
        ], $defaults->all());
    }

    /**
     */
    #[Test]
    public function a_resource_can_get_the_allowed_values()
    {
        $allowed = $this->user->settings()->getAllowed('test_settings1');

        $this->assertEquals(['bananas', 'grapes', 8, 'monkey'], $allowed->all());
    }

    /**
     */
    #[Test]
    public function a_resource_can_get_all_allowed_values()
    {
        $allowed = $this->user->settings()->allAllowed()->flatten();

        $this->assertCount(14, $allowed);
    }

    /**
     */
    #[Test]
    public function adding_a_new_setting_creates_a_new_user_setting_record()
    {
        $this->user->settings()->set(['test_settings3' => true]);

        $this->assertDatabaseHas('property_bag', [
            'resource_id'   => $this->user->id,
            'resource_type' => 'LaravelPropertyBag\tests\Classes\User',
            'value'         => json_encode('[true]'),
        ]);
    }

    /**
     */
    #[Test]
    public function adding_a_new_setting_refreshes_settings_on_object()
    {
        $this->assertEmpty($this->user->settings()->allSaved());

        $this->user->settings()->set(['test_settings3' => true]);

        $this->assertEquals(
            ['test_settings3' => true],
            $this->user->settings()->allSaved()->all()
        );
    }

    /**
     */
    #[Test]
    public function updating_a_setting_updates_the_setting_record()
    {

        $settings = $this->user->settings();

        $settings->set(['test_settings1' => 'bananas']);

        $this->assertEquals(
            ['test_settings1' => 'bananas'],
            $settings->allSaved()->all()
        );

        $this->assertDatabaseHas('property_bag', [
            'resource_id'   => $this->user->id,
            'resource_type' => 'LaravelPropertyBag\tests\Classes\User',
            'key'           => 'test_settings1',
            'value'         => json_encode('["bananas"]'),
        ]);

        $settings->set(['test_settings1' => 'grapes']);

        $this->assertEquals(
            ['test_settings1' => 'grapes'],
            $settings->allSaved()->all()
        );

        $this->assertDatabaseHas('property_bag', [
            'resource_id'   => $this->user->id,
            'resource_type' => 'LaravelPropertyBag\tests\Classes\User',
            'key'           => 'test_settings1',
            'value'         => json_encode('["grapes"]'),
        ]);
    }

    /**
     */
    #[Test]
    public function a_user_can_set_many_settings_at_once()
    {

        $settings = $this->user->settings();

        $this->assertEmpty($settings->allSaved());

        $test = [
            'test_settings1' => 'grapes',
            'test_settings2' => false,
        ];

        $settings->set($test);

        $this->assertEquals($test, $settings->allSaved()->all());

        $this->assertDatabaseHas('property_bag', [
            'resource_id'   => $this->user->id,
            'resource_type' => 'LaravelPropertyBag\tests\Classes\User',
            'key'           => 'test_settings1',
            'value'         => json_encode('["grapes"]'),
        ]);

        $this->assertDatabaseHas('property_bag', [
            'resource_id'   => $this->user->id,
            'resource_type' => 'LaravelPropertyBag\tests\Classes\User',
            'key'           => 'test_settings2',
            'value'         => json_encode('[false]'),
        ]);
    }

    /**
     */
    #[Test]
    public function a_user_can_get_a_setting()
    {

        $settings = $this->user->settings();

        $settings->set(['test_settings2' => false]);

        $result = $settings->get('test_settings2');

        $this->assertFalse($result);
    }

    /**
     */
    #[Test]
    public function if_the_setting_is_not_set_the_default_value_is_returned()
    {

        $result = $this->user->settings()->get('test_settings1');

        $this->assertEquals('monkey', $result);
    }

    /**
     */
    #[Test]
    public function a_user_can_get_all_the_settings_being_used()
    {

        $settings = $this->user->settings();

        $settings->set([
            'test_settings1' => 'bananas',
        ]);

        $this->assertEquals([
            'test_settings1' => 'bananas',
            'test_settings2' => true,
            'test_settings3' => false,
        ], $this->user->settings()->all()->all());
    }

    /**
     */
    #[Test]
    public function a_user_can_get_all_the_settings_saved_in_the_database()
    {

        $settings = $this->user->settings();

        $settings->set([
            'test_settings1' => 'bananas',
        ]);

        $this->assertEquals([
            'test_settings1' => 'bananas',
        ], $this->user->settings()->allSaved()->all());
    }

    /**
     */
    #[Test]
    public function a_user_can_not_get_an_invalid_setting()
    {

        $settings = $this->user->settings();

        $result = $settings->get('invalid_setting');

        $this->assertNull($result);
    }

    /**
     */
    #[Test]
    public function if_default_value_is_set_database_entry_is_deleted()
    {

        $settings = $this->user->settings();

        $settings->set([
            'test_settings1' => 'grapes',
        ]);

        $this->assertDatabaseHas('property_bag', [
            'resource_id' => $this->user->id,
            'key'         => 'test_settings1',
            'value'       => json_encode('["grapes"]'),
        ]);

        $settings->set([
            'test_settings1' => 'monkey',
        ]);

        $this->assertDatabaseMissing('property_bag', [
            'resource_id'   => $this->user->id,
            'resource_type' => 'LaravelPropertyBag\tests\Classes\User',
            'key'           => 'test_settings1',
            'value'         => json_encode('["monkey"]'),
        ]);
    }

    /**
     */
    #[Test]
    public function setting_an_unallowed_setting_value_throws_exception()
    {
        $this->expectException(InvalidSettingsValue::class);
        $this->expectExceptionMessage('Given value is not a registered allowed value for test_settings1.');
        

        $this->user->settings()->set([
            'test_settings1' => 'invalid',
        ]);
    }

    /**
     */
    #[Test]
    public function invalid_setting_value_exception_should_contain_failed_key_name()
    {

        try {
            $this->user->settings()->set([
                'test_settings1' => 'invalid',
            ]);
        } catch (InvalidSettingsValue $e) {
            $this->assertEquals('test_settings1', $e->getFailedKey());
        }
    }

    /**
     */
    #[Test]
    public function settings_can_be_registered_in_config_file_method()
    {
        $post = $this->makePost();

        $defaults = $post->defaultSetting();

        $this->assertEquals([
            'test_settings1' => 'monkey',
            'test_settings2' => true,
            'test_settings3' => false,
        ], $defaults->all());

        $allowed = $post->allowedSetting();

        $actual = [
            'test_settings1' => ['bananas', 'grapes', 8, 'monkey'],
            'test_settings2' => [true, false],
            'test_settings3' => [true, false, 'true', 'false', 0, 1, '0', '1'],
        ];

        $this->assertEquals($actual, $allowed->all());
    }

    /**
     */
    #[Test]
    public function settings_with_allowed_rule_can_be_set()
    {
        $comment = $this->makeComment();

        $settings = [
            'alpha'    => 'abc',
            'alphanum' => 'abc123',
            'any'      => 45,
            'bool'     => false,
            'integer'  => 10,
            'numeric'  => '87',
            'range'    => 4,
            'range2'   => -1,
            'string'   => 'test',
        ];

        $comment->settings()->set($settings);

        $settings['invalid'] = null;
        $settings['user_defined'] = true;

        $this->assertEquals($settings, $comment->settings()->all()->all());
    }

    /**
     */
    #[Test]
    public function settings_with_invalid_rule_values_can_not_be_set()
    {
        $this->expectException(InvalidSettingsValue::class);
        
        $comment = $this->makeComment();

        $comment->settings()->set(['alpha' => 4]);
    }

    /**
     */
    #[Test]
    public function keyIs_returns_true_if_key_value_is_set()
    {

        $this->user->settings()->set(['test_settings1' => 'bananas']);

        $this->assertEquals(
            ['test_settings1' => 'bananas'],
            $this->user->settings()->allSaved()->all()
        );

        $this->assertTrue(
            $this->user->settings()->keyIs('test_settings1', 'bananas')
        );
    }

    /**
     */
    #[Test]
    public function keyIs_returns_false_if_key_value_is_not_set()
    {

        $this->user->settings()->set(['test_settings1' => 'bananas']);

        $this->assertEquals(
            ['test_settings1' => 'bananas'],
            $this->user->settings()->allSaved()->all()
        );

        $this->assertFalse(
            $this->user->settings()->keyIs('test_settings1', 'grapes')
        );
    }

    /**
     */
    #[Test]
    public function reset_resets_setting_to_default_value()
    {

        $this->user->settings()->set(['test_settings1' => 'bananas']);

        $this->assertEquals(
            ['test_settings1' => 'bananas'],
            $this->user->settings()->allSaved()->all()
        );

        $this->user->settings()->reset('test_settings1');

        $this->assertEquals('monkey', $this->user->settings('test_settings1'));
    }

    /**
     */
    #[Test]
    public function created_setting_is_immediately_available_for_reading()
    {
        $settings = $this->user->settings();

        $this->assertEquals(false, $settings->get('test_settings3'));

        $settings->set(['test_settings3' => true]);
        $this->assertEquals(true, $settings->get('test_settings3'));
    }

    /**
     */
    #[Test]
    public function updated_setting_is_immediately_available_for_reading()
    {
        $settings = $this->user->settings();

        $settings->set(['test_settings1' => 'bananas']);
        $this->assertEquals('bananas', $settings->get('test_settings1'));

        $settings->set(['test_settings1' => 'grapes']);
        $this->assertEquals('grapes', $settings->get('test_settings1'));
    }

    /**
     */
    #[Test]
    public function deleted_setting_is_immediately_available_for_reading()
    {
        $settings = $this->user->settings();

        $this->assertEquals('monkey', $settings->get('test_settings1'));

        $settings->set(['test_settings1' => 'grapes']);
        $this->assertEquals('grapes', $settings->get('test_settings1'));

        $settings->set(['test_settings1' => 'monkey']);
        $this->assertEquals('monkey', $settings->get('test_settings1'));
    }

    /**
     */
    #[Test]
    public function created_setting_is_immediately_available_for_reading_with_preloaded_relation()
    {
        $this->user->load('propertyBag');
        $settings = $this->user->settings();

        $this->assertEquals(false, $settings->get('test_settings3'));

        $settings->set(['test_settings3' => true]);
        $this->assertEquals(true, $settings->get('test_settings3'));
    }

    /**
     */
    #[Test]
    public function updated_setting_is_immediately_available_for_reading_with_preloaded_relation()
    {
        $this->user->load('propertyBag');
        $settings = $this->user->settings();

        $settings->set(['test_settings1' => 'bananas']);
        $this->assertEquals('bananas', $settings->get('test_settings1'));

        $settings->set(['test_settings1' => 'grapes']);
        $this->assertEquals('grapes', $settings->get('test_settings1'));
    }

    /**
     */
    #[Test]
    public function deleted_setting_is_immediately_available_for_reading_with_preloaded_relation()
    {
        $this->user->load('propertyBag');
        $settings = $this->user->settings();

        $this->assertEquals('monkey', $settings->get('test_settings1'));

        $settings->set(['test_settings1' => 'grapes']);
        $this->assertEquals('grapes', $settings->get('test_settings1'));

        $settings->set(['test_settings1' => 'monkey']);
        $this->assertEquals('monkey', $settings->get('test_settings1'));
    }
}
