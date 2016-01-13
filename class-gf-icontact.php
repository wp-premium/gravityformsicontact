<?php
	
GFForms::include_feed_addon_framework();

class GFiContact extends GFFeedAddOn {
	
	protected $_version = GF_ICONTACT_VERSION;
	protected $_min_gravityforms_version = '1.9.6.10';
	protected $_slug = 'gravityformsicontact';
	protected $_path = 'gravityformsicontact/icontact.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms iContact Add-On';
	protected $_short_title = 'iContact';

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_icontact', 'gravityforms_icontact_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_icontact';
	protected $_capabilities_form_settings = 'gravityforms_icontact';
	protected $_capabilities_uninstall = 'gravityforms_icontact_uninstall';
	protected $_enable_rg_autoupgrade = true;

	protected $api = null;
	protected $_new_custom_fields = array();
	private static $_instance = null;

	public static function get_instance() {
		
		if ( self::$_instance == null )
			self::$_instance = new GFiContact();

		return self::$_instance;
		
	}

	/* Settings Page */
	public function plugin_settings_fields() {
						
		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'app_id',
						'label'             => __( 'Application ID', 'gravityformsicontact' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'api_username',
						'label'             => __( 'API Username', 'gravityformsicontact' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'api_password',
						'label'             => __( 'API Password', 'gravityformsicontact' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'type'              => 'save',
						'messages'          => array(
							'success' => __( 'iContact settings have been updated.', 'gravityformsicontact' )
						),
					),
				),
			),
		);
		
	}
	
	/* Prepare plugin settings description */
	public function plugin_settings_description() {
		
		$description  = '<p>';
		$description .= sprintf(
			__( 'iContact makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add them to your iContact list. If you don\'t have an iConact account, you can %1$s sign up for one here.%2$s', 'gravityformsicontact' ),
			'<a href="http://www.icontact.com/" target="_blank">', '</a>'
		);
		$description .= '</p>';
		
		if ( ! $this->initialize_api() ) {
			
			$description .= '<p>';
			$description .= __( 'Gravity Forms iContact Add-On requires your Application ID, API username and API password. To obtain an application ID, follow the steps below:', 'gravityformsicontact' );
			$description .= '</p>';
			
			$description .= '<ol>';
			$description .= '<li>' . sprintf(
				__( 'Visit iContact\'s %1$s application registration page.%2$s', 'gravityformsicontact' ),
				'<a href="https://app.icontact.com/icp/core/registerapp/" target="_blank">', '</a>'
			) . '</li>';
			$description .= '<li>' . __( 'Set an application name and description for your application.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . __( 'Choose to show information for API 2.0.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . __( 'Copy the provided API-AppId into the Application ID setting field below.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . __( 'Click "Enable this AppId for your account".', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . __( 'Create a password for your application and click save.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . __( 'Enter your API password, along with your iContact account username, into the settings fields below.', 'gravityformsicontact' ) . '</li>';
			$description .= '</ol>';
			
		}
				
		return $description;
		
	}

	/* Setup feed settings fields */
	public function feed_settings_fields() {
		
		return array(
			array(	
				'title'  => '',
				'fields' => array(
					array(
						'name'           => 'feed_name',
						'label'          => __( 'Feed Name', 'gravityformsicontact' ),
						'type'           => 'text',
						'required'       => true,
						'tooltip'        => '<h6>'. __( 'Name', 'gravityformsicontact' ) .'</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gravityformsicontact' )
					),
					array(
						'name'           => 'list',
						'label'          => __( 'iContact List', 'gravityformsicontact' ),
						'type'           => 'select',
						'required'       => true,
						'choices'        => $this->lists_for_feed_setting(),
						'tooltip'        => '<h6>'. __( 'iContact List', 'gravityformsicontact' ) .'</h6>' . __( 'Select which iContact list this feed will add contacts to.', 'gravityformsicontact' )
					),
					array(
						'name'           => 'fields',
						'label'          => __( 'Map Fields', 'gravityformsicontact' ),
						'type'           => 'field_map',
						'field_map'      => $this->fields_for_feed_mapping(),
						'tooltip'        => '<h6>'. __( 'Map Fields', 'gravityformsicontact' ) .'</h6>' . __( 'Select which Gravity Form fields pair with their respective iContact fields.', 'gravityformsicontact' )
					),
					array(
						'name'           => 'custom_fields',
						'label'          => __( 'Custom Fields', 'gravityformsicontact' ),
						'type'           => 'dynamic_field_map',
						'field_map'      => $this->custom_fields_for_feed_setting(),
						'tooltip'        => '<h6>'. __( 'Custom Fields', 'gravityformsicontact' ) .'</h6>' . __( 'Select or create a new iContact custom field to pair with Gravity Forms fields.', 'gravityformsicontact' )
					),
					array(
						'name'           => 'feed_condition',
						'label'          => __( 'Opt-In Condition', 'gravityformsicontact' ),
						'type'           => 'feed_condition',
						'checkbox_label' => __( 'Enable', 'gravityformsicontact' ),
						'instructions'   => __( 'Export to iContact if', 'gravityformsicontact' ),
						'tooltip'        => '<h6>'. __( 'Opt-In Condition', 'gravityformsicontact' ) .'</h6>' . __( 'When the opt-in condition is enabled, form submissions will only be exported to iContact when the condition is met. When disabled, all form submissions will be exported.', 'gravityformsicontact' )
					),
				)	
			)
		);
		
	}
	
	/* Fork of maybe_save_feed_settings to create new iContact custom fields */
	public function maybe_save_feed_settings( $feed_id, $form_id ) {

		if ( ! rgpost( 'gform-settings-save' ) ) {
			return $feed_id;
		}

		// store a copy of the previous settings for cases where action would only happen if value has changed
		$feed = $this->get_feed( $feed_id );
		$this->set_previous_settings( $feed['meta'] );

		$settings = $this->get_posted_settings();
		$settings = $this->create_new_custom_fields( $settings );
		$sections = $this->get_feed_settings_fields();
		$settings = $this->trim_conditional_logic_vales( $settings, $form_id );

		$is_valid = $this->validate_settings( $sections, $settings );
		$result   = false;

		if ( $is_valid ) {
			$feed_id = $this->save_feed_settings( $feed_id, $form_id, $settings );
			if ( $feed_id ){
				GFCommon::add_message( $this->get_save_success_message( $sections ) );
			}
			else{
				GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
			}
		}
		else{
			GFCommon::add_error_message( $this->get_save_error_message( $sections ) );
		}

		return $feed_id;
	}

	/* Prepare iContact lists for feed field */
	public function lists_for_feed_setting() {
				
		$lists = array();
		
		/* If iContact API credentials are invalid, return the lists array. */
		if ( ! $this->initialize_api() )
			return $lists;
		
		try {
			
			/* Get available iContact lists. */
			$icontact_lists = $this->api->get_lists();
						
			/* Add iContact lists to array and return it. */
			foreach ( $icontact_lists as $list ) {
				
				$lists[] = array(
					'label' => $list['name'],
					'value' => $list['listId']
				);
				
			}
			

		} catch ( Exception $e ) {
			
			$this->log_error( __METHOD__ . '(): Unable to retrieve lists; '. $e->getMessage() );			
		
		}
		
		return $lists;
		
	}

	/* Prepare fields for feed field mapping */
	public function fields_for_feed_mapping() {
		
		return array(
			array(	
				'name'          => 'email',
				'label'         => __( 'Email Address', 'gravityformsicontact' ),
				'required'      => true,
				'field_type'    => array( 'email' ),
				'default_value' => $this->get_first_email_field()
			),
			array(	
				'name'       => 'prefix',
				'label'      => __( 'Prefix', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'first_name',
				'label'      => __( 'First Name', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'last_name',
				'label'      => __( 'Last Name', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'suffix',
				'label'      => __( 'Suffix', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'street',
				'label'      => __( 'Address: Street Address', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'street2',
				'label'      => __( 'Address: Line 2', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'city',
				'label'      => __( 'Address: City', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'state',
				'label'      => __( 'Address: State', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'postal_code',
				'label'      => __( 'Address: Postal Code', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'phone',
				'label'      => __( 'Phone Number', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'fax',
				'label'      => __( 'Fax Number', 'gravityformsicontact' ),
			),
			array(	
				'name'       => 'business',
				'label'      => __( 'Business Number', 'gravityformsicontact' ),
			),
		);
		
	}

	/* Prepare custom fields for feed field mapping */
	public function custom_fields_for_feed_setting() {
		
		$fields = array();
		
		/* If iContact API credentials are invalid, return the fields array. */
		if ( ! $this->initialize_api() )
			return $fields;		
		
		/* Get available iContact fields. */
		$icontact_fields = $this->api->get_custom_fields();
		
		/* If no iContact fields exist, return the fields array. */
		if ( empty( $icontact_fields ) )
			return $fields;
			
		/* Add iContact fields to the fields array. */
		foreach ( $icontact_fields as $field ) {
			
			$fields[] = array(
				'label' => $field['publicName'],
				'value' => $field['customFieldId']
			);
			
		}
		
		/* Add new custom fields to the fields array. */
		if ( ! empty( $this->_new_custom_fields ) ) {
			
			foreach ( $this->_new_custom_fields as $new_field ) {
				
				$found_custom_field = false;
				foreach ( $fields as $field ) {
					
					if ( $field['value'] == $new_field['value'] )
						$found_custom_field = true;
					
				}
				
				if ( ! $found_custom_field )
					$fields[] = array(
						'label' => $new_field['label'],
						'value' => $new_field['value']	
					);
				
			}
			
		}
		
		if ( empty( $fields ) )
			return $fields;
						
		/* Add "Add Custom Field" to array. */
		$fields[] = array(
			'label' => __( 'Add Custom Field', 'gravityformsicontact' ),
			'value' => 'gf_custom'	
		);
		
		return $fields;
		
	}

	/* Create new iContact custom fields */
	public function create_new_custom_fields( $settings ) {

		global $_gaddon_posted_settings;

		/* If no custom fields are set or if the API credentials are invalid, return settings. */
		if ( empty( $settings['custom_fields'] ) || ! $this->initialize_api() )
			return $settings;
	
		/* Loop through each custom field. */
		foreach ( $settings['custom_fields'] as $index => &$field ) {
			
			/* If no custom key is set, move on. */
			if ( rgblank( $field['custom_key'] ) )
				continue;
				
			$custom_key = $field['custom_key'];
			
			$private_name = strtolower( str_replace(
				array( ' ', '"', "'", '\\', '/', '[', ']' ),
				'',
				$custom_key	
			) );
			
			/* Prepare new field to add. */
			$custom_field = array(
				'fieldType'     => 'text',
				'displayToUser' => 1,
				'privateName'   => $private_name,
				'publicName'    => $custom_key
			);

			/* Add new field. */
			$new_field = $this->api->add_custom_field( $custom_field );
						
			/* Replace key for field with new shortcut name and reset custom key. */
			$field['key'] = $private_name;
			$field['custom_key'] = '';
			
			/* Update POST field to ensure front-end display is up-to-date. */
			$_gaddon_posted_settings['custom_fields'][ $index ]['key'] = $private_name;
			$_gaddon_posted_settings['custom_fields'][ $index ]['custom_key'] = '';
			
			/* Push to new custom fields array to update the UI. */			
			$this->_new_custom_fields[] = array(
				'label' => $custom_key,
				'value' => $private_name,
			);
			
		}
				
		return $settings;
		
	}

	/* Setup feed list columns */
	public function feed_list_columns() {
		
		return array(
			'feed_name' => __( 'Name', 'gravityformsicontact' ),
			'list'      => __( 'iContact List', 'gravityformsicontact' )
		);
		
	}
	
	/* Change value of list feed column to list name */
	public function get_column_value_list( $feed ) {
			
		/* If iContact instance is not initialized, return list ID. */
		if ( ! $this->initialize_api() )
			return $feed['meta']['list'];
		
		try {
			
			/* Get iContact list. */
			$list = $this->api->get_list( $feed['meta']['list'] );
			
			return $list['name'];
			
		} catch ( Exception $e ) {
			
			$this->log_error( __METHOD__ . '(): Unable to retrieve list; '. $e->getMessage() );			
		
			return $feed['meta']['list'];
		
		}
		
	}

	/* Hide "Add New" feed button if API credentials are invalid */		
	public function feed_list_title() {
		
		if ( $this->initialize_api() )
			return parent::feed_list_title();
			
		return sprintf( __( '%s Feeds', 'gravityforms' ), $this->get_short_title() );
		
	}

	/* Notify user to configure add-on before setting up feeds */
	public function feed_list_message() {

		$message = parent::feed_list_message();
		
		if ( $message !== false )
			return $message;

		if ( ! $this->initialize_api() )
			return $this->configure_addon_message();

		return false;
		
	}
	
	/* Feed list message for user to configure add-on */
	public function configure_addon_message() {
		
		$settings_label = sprintf( __( '%s Settings', 'gravityforms' ), $this->get_short_title() );
		$settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		return sprintf( __( 'To get started, please configure your %s.', 'gravityformsicontact' ), $settings_link );
		
	}

	/* Get first email address field for form. */
	public function get_first_email_field() {
		
		/* Get the current form ID. */
		$form_id = rgget( 'id' );
		
		/* Get the form. */
		$form = GFAPI::get_form( $form_id );
		
		/* Get email fields for the form. */
		$email_fields = GFCommon::get_fields_by_type( $form, array( 'email' ) );
		
		if ( ! empty( $email_fields ) ) {
			
			return $email_fields[0]->id;
			
		}
		
		return null;
		
	}

	/* Process feed */
	public function process_feed( $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Processing feed.' );
		
		/* If API instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {
			
			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );
			return;
			
		}
		
		/* Setup mapped fields array. */
		$mapped_fields = $this->get_field_map_fields( $feed, 'fields' );
		
		/* Setup contact array. */
		$contact = array(
			'email'      => $this->get_field_value( $form, $entry, $mapped_fields['email'] ),
			'prefix'     => $this->get_field_value( $form, $entry, $mapped_fields['prefix'] ),
			'firstName'  => $this->get_field_value( $form, $entry, $mapped_fields['first_name'] ),
			'lastName'   => $this->get_field_value( $form, $entry, $mapped_fields['last_name'] ),
			'suffix'     => $this->get_field_value( $form, $entry, $mapped_fields['suffix'] ),
			'street'     => $this->get_field_value( $form, $entry, $mapped_fields['street'] ),
			'street2'    => $this->get_field_value( $form, $entry, $mapped_fields['street2'] ),
			'city'       => $this->get_field_value( $form, $entry, $mapped_fields['city'] ),
			'state'      => $this->get_field_value( $form, $entry, $mapped_fields['state'] ),
			'postalCode' => $this->get_field_value( $form, $entry, $mapped_fields['postal_code'] ),
			'phone'      => $this->get_field_value( $form, $entry, $mapped_fields['phone'] ),
			'fax'        => $this->get_field_value( $form, $entry, $mapped_fields['fax'] ),
			'business'   => $this->get_field_value( $form, $entry, $mapped_fields['business'] )
		);

		/* Add custom fields to contact array. */
		foreach ( $feed['meta']['custom_fields'] as $custom_field ) {
			
			if ( rgblank( $custom_field['key'] ) || $custom_field['key'] == 'gf_custom' || rgblank( $custom_field['value'] ) )
				continue;

			$field_value = $this->get_field_value( $form, $entry, $custom_field['value'] );
			
			if ( rgblank( $field_value ) )
				continue;
				
			$contact[$custom_field['key']] = $field_value;
			
		}

		/* If the email address is empty, exit. */
		if ( rgblank( $contact['email'] ) ) {
			
			$this->log_error( __METHOD__ . '(): Email address not provided.' );
			return;			
		
		}
		
		/* Check to see if we're adding a new contact. */
		$find_contact = $this->api->get_contact_by_email( $contact['email'] );
		$is_new_contact = empty( $find_contact );
			
		if ( $is_new_contact ) {
			
			/* Log that we're creating a new contact. */
			$this->log_debug( __METHOD__ . "(): {$contact['email']} does not exist and will be created." );
			
			try {
				
				/* Add the contact. */
				$new_contact = $this->api->add_contact( $contact );

				/* Log that contact was created. */
				$this->log_debug( __METHOD__ . "(): {$contact['email']} has been created; contact ID: {$new_contact['contactId']}." );

			} catch ( Exception $e ) {
				
				/* Log error. */
				$this->log_error( __METHOD__ . "(): {$contact['email']} was not added; {$e->getMessage()}" );
				
				/* Stop processing feed. */
				return false;
				
			}

			try {
				
				/* Subscribe the new contact to the list. */
				$subscription = $this->api->add_contact_to_list( $new_contact['contactId'], $feed['meta']['list'] );

				/* Log that contact was subscribed to list. */
				$this->log_debug( __METHOD__ . "(): {$contact['email']} has been subscribed to list; subscription ID: {$subscription[0]['subscriptionId']}." );

			} catch ( Exception $e ) {
				
				/* Log error. */
				$this->log_error( __METHOD__ . "(): {$contact['email']} was not subscribed to list; {$e->getMessage()}" );
				
				/* Stop processing feed. */
				return false;
				
			}
			
			
		} else {
			
			/* Log that we're updating an existing contact. */
			$this->log_debug( __METHOD__ . "(): {$contact['email']} already exists and will be updated." );

			$contact_id = $find_contact[0]['contactId'];

			try {
				
				/* Update the contact. */
				$update_contact = $this->api->update_contact( $contact_id, $contact );

				/* Log that contact was created. */
				$this->log_debug( __METHOD__ . "(): {$contact['email']} has been updated; contact ID: {$contact_id}." );

			} catch ( Exception $e ) {
				
				/* Log error. */
				$this->log_error( __METHOD__ . "(): {$contact['email']} was not updated; {$e->getMessage()}" );
				
				/* Stop processing feed. */
				return false;
				
			}

			try {
				
				/* Subscribe the contact to the list. */
				$subscription = $this->api->add_contact_to_list( $contact_id, $feed['meta']['list'] );

				/* Log whether or not contact was subscribed to list. */
				if ( empty ( $subscription ) ) {
					
					$this->log_debug( __METHOD__ . "(): {$contact['email']} was already subscribed to list." );
				
				} else {
					
					$this->log_debug( __METHOD__ . "(): {$contact['email']} has been subscribed to list; subscription ID: {$subscription[0]['subscriptionId']}." );
					
				}

			} catch ( Exception $e ) {
				
				/* Log error. */
				$this->log_error( __METHOD__ . "(): {$contact['email']} was not subscribed to list; {$e->getMessage()}" );
				
				/* Stop processing feed. */
				return false;
				
			}
			
		}

	}

	/* Checks validity of iContact API credentials and initializes API if valid. */
	public function initialize_api() {

		if ( ! is_null( $this->api ) )
			return true;
		
		/* Load the iContact API library. */
		require_once 'includes/class-icontact.php';

		/* Get the plugin settings */
		$settings = $this->get_plugin_settings();
		
		/* If any of the account information fields are empty, return null. */
		if ( rgblank( $settings['app_id'] ) || rgblank( $settings['api_username'] ) || rgblank( $settings['api_password'] ) )
			return null;
			
		$this->log_debug( __METHOD__ . "(): Validating API info for {$settings['app_id']} / {$settings['api_username']}." );
		
		/* Create a new iContact object. */
		$icontact = new iContact( $settings['app_id'], $settings['api_username'], $settings['api_password'] );
		
		try {
			
			/* Run a test request. */
			$contacts = $icontact->get_contacts();
			
			/* Log that test passed. */
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );
			
			/* Assign iContact object to the class. */
			$this->api = $icontact;
			
			return true;
			
		} catch ( Exception $e ) {
			
			/* Log that test failed. */
			$this->log_error( __METHOD__ . '(): API credentials are invalid; '. $e->getMessage() );			

			return false;
			
		}
		
	}

}