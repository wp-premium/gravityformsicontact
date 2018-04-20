<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}
	
GFForms::include_feed_addon_framework();

class GFiContact extends GFFeedAddOn {
	
	protected $_version = GF_ICONTACT_VERSION;
	protected $_min_gravityforms_version = '1.9.14.26';
	protected $_slug = 'gravityformsicontact';
	protected $_path = 'gravityformsicontact/icontact.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms iContact Add-On';
	protected $_short_title = 'iContact';
	protected $_enable_rg_autoupgrade = true;
	protected $_new_custom_fields = array();
	protected $api = null;
	private static $_instance = null;

	/* Permissions */
	protected $_capabilities_settings_page = 'gravityforms_icontact';
	protected $_capabilities_form_settings = 'gravityforms_icontact';
	protected $_capabilities_uninstall = 'gravityforms_icontact_uninstall';

	/* Members plugin integration */
	protected $_capabilities = array( 'gravityforms_icontact', 'gravityforms_icontact_uninstall' );

	/**
	 * Get instance of this class.
	 * 
	 * @access public
	 * @static
	 * @return $_instance
	 */
	public static function get_instance() {
		
		if ( self::$_instance == null ) {
			self::$_instance = new self;
		}

		return self::$_instance;
		
	}

	/**
	 * Register needed plugin hooks and PayPal delayed payment support.
	 * 
	 * @access public
	 * @return void
	 */
	public function init() {
		
		parent::init();
		
		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe contact to iContact only when payment is received.', 'gravityformsicontact' )
			)
		);
		
	}

	/**
	 * Register needed styles.
	 * 
	 * @access public
	 * @return array $styles
	 */
	public function styles() {
		
		$styles = array(
			array(
				'handle'  => 'gform_icontact_form_settings_css',
				'src'     => $this->get_base_url() . '/css/form_settings.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				)
			)
		);
		
		return array_merge( parent::styles(), $styles );
		
	}

	/**
	 * Setup plugin settings fields.
	 * 
	 * @access public
	 * @return array
	 */
	public function plugin_settings_fields() {
						
		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'app_id',
						'label'             => esc_html__( 'Application ID', 'gravityformsicontact' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'api_username',
						'label'             => esc_html__( 'Account Email Address', 'gravityformsicontact' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'api_password',
						'label'             => esc_html__( 'API Password', 'gravityformsicontact' ),
						'type'              => 'text',
						'class'             => 'medium',
						'input_type'        => 'password',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'client_folder',
						'label'             => esc_html__( 'Client Folder', 'gravityformsicontact' ),
						'type'              => 'select',
						'choices'           => $this->client_folders_for_plugin_setting(),
						'dependency'        => array( $this, 'initialize_api' )
					),
					array(
						'type'              => 'save',
						'messages'          => array(
							'success' => esc_html__( 'iContact settings have been updated.', 'gravityformsicontact' )
						),
					),
				),
			),
		);
		
	}
	
	/**
	 * Prepare plugin settings description.
	 * 
	 * @access public
	 * @return string $description
	 */
	public function plugin_settings_description() {
		
		$description  = '<p>';
		$description .= sprintf(
			esc_html__( 'iContact makes it easy to send email newsletters to your customers, manage your subscriber lists, and track campaign performance. Use Gravity Forms to collect customer information and automatically add it to your iContact list. If you don\'t have an iContact account, you can %1$s sign up for one here.%2$s', 'gravityformsicontact' ),
			'<a href="http://www.icontact.com/" target="_blank">', '</a>'
		);
		$description .= '</p>';
		
		if ( ! $this->initialize_api() ) {
			
			$description .= '<p>';
			$description .= esc_html__( 'Gravity Forms iContact Add-On requires your Application ID, API username and API password. To obtain an application ID, follow the steps below:', 'gravityformsicontact' );
			$description .= '</p>';
			
			$description .= '<ol>';
			$description .= '<li>' . sprintf(
				esc_html__( 'Visit iContact\'s %1$s application registration page.%2$s', 'gravityformsicontact' ),
				'<a href="https://app.icontact.com/icp/core/registerapp/" target="_blank">', '</a>'
			) . '</li>';
			$description .= '<li>' . esc_html__( 'Set an application name and description for your application.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . esc_html__( 'Choose to show information for API 2.0.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . esc_html__( 'Copy the provided API-AppId into the Application ID setting field below.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . esc_html__( 'Click "Enable this AppId for your account".', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . esc_html__( 'Create a password for your application and click save.', 'gravityformsicontact' ) . '</li>';
			$description .= '<li>' . esc_html__( 'Enter your API password, along with your iContact account username, into the settings fields below.', 'gravityformsicontact' ) . '</li>';
			$description .= '</ol>';
			
		}
				
		return $description;
		
	}

	/**
	 * Prepare client folders for plugin settings.
	 * 
	 * @access public
	 * @return void
	 */
	public function client_folders_for_plugin_setting() {
		
		$choices = array(
			array(
				'label' => esc_html__( 'Select a Client Folder', 'gravityformsicontact' ),
				'value' => ''
			)
		);
		
		/* If API is not initialized, return choices array. */
		if ( ! $this->initialize_api() ) {
			return $choices;
		}
		
		/* Get client folders. */
		try {
			
			$client_folders = $this->api->get_client_folders();
			
		} catch ( Exception $e ) {
			
			$this->log_error( __METHOD__ . '(): Unable to get client folders; ' . $e->getMessage() );
			
			return $choices;
			
		}
				
		/* Add client folders to choices array. */
		foreach ( $client_folders as $folder ) {
			
			$choices[] = array(
				'label' => rgar( $folder, 'name' ) ? $folder['name'] : esc_html__( 'Default Client Folder', 'gravityformsicontact' ),
				'value' => $folder['clientFolderId']
			);
			
		}
		
		return $choices;
		
	}

	/**
	 * Setup fields for feed settings.
	 * 
	 * @access public
	 * @return array
	 */
	public function feed_settings_fields() {
		
		return array(
			array(	
				'title'  => '',
				'fields' => array(
					array(
						'name'           => 'feed_name',
						'label'          => esc_html__( 'Feed Name', 'gravityformsicontact' ),
						'type'           => 'text',
						'required'       => true,
						'tooltip'        => '<h6>'. esc_html__( 'Name', 'gravityformsicontact' ) .'</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsicontact' ),
						'default_value'  => $this->get_default_feed_name(),
					),
					array(
						'name'           => 'list',
						'label'          => esc_html__( 'iContact List', 'gravityformsicontact' ),
						'type'           => 'select',
						'required'       => true,
						'choices'        => $this->lists_for_feed_setting(),
						'tooltip'        => '<h6>'. esc_html__( 'iContact List', 'gravityformsicontact' ) .'</h6>' . esc_html__( 'Select which iContact list this feed will add contacts to.', 'gravityformsicontact' )
					),
					array(
						'name'           => 'fields',
						'label'          => esc_html__( 'Map Fields', 'gravityformsicontact' ),
						'type'           => 'field_map',
						'field_map'      => $this->fields_for_feed_mapping(),
						'tooltip'        => '<h6>'. esc_html__( 'Map Fields', 'gravityformsicontact' ) .'</h6>' . esc_html__( 'Select which Gravity Form fields pair with their respective iContact fields.', 'gravityformsicontact' )
					),
					array(
						'name'           => 'custom_fields',
						'label'          => '',
						'type'           => 'dynamic_field_map',
						'field_map'      => $this->custom_fields_for_feed_setting(),
					),
					array(
						'name'           => 'feed_condition',
						'label'          => esc_html__( 'Opt-In Condition', 'gravityformsicontact' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable', 'gravityformsicontact' ),
						'instructions'   => esc_html__( 'Export to iContact if', 'gravityformsicontact' ),
						'tooltip'        => '<h6>'. esc_html__( 'Opt-In Condition', 'gravityformsicontact' ) .'</h6>' . esc_html__( 'When the opt-in condition is enabled, form submissions will only be exported to iContact when the condition is met. When disabled, all form submissions will be exported.', 'gravityformsicontact' )
					),
				)	
			)
		);
		
	}
	
	/**
	 * Fork of maybe_save_feed_settings to create new iContact custom fields.
	 * 
	 * @access public
	 * @param int $feed_id
	 * @param int $form_id
	 * @return int $feed_id
	 */
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

	/**
	 * Prepare iContact lists for feed field.
	 * 
	 * @access public
	 * @return array $lists
	 */
	public function lists_for_feed_setting() {
				
		$lists = array();
		
		/* If iContact API credentials are invalid, return the lists array. */
		if ( ! $this->initialize_api() ) {
			return $lists;
		}
		
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

	/**
	 * Prepare fields for feed field mapping.
	 * 
	 * @access public
	 * @return array
	 */
	public function fields_for_feed_mapping() {
		
		return array(
			array(	
				'name'          => 'email',
				'label'         => esc_html__( 'Email Address', 'gravityformsicontact' ),
				'required'      => true,
				'field_type'    => array( 'email' ),
				'default_value' => $this->get_first_field_by_type( 'email' )
			),
			array(	
				'name'          => 'prefix',
				'label'         => esc_html__( 'Prefix', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'first_name',
				'label'         => esc_html__( 'First Name', 'gravityformsicontact' ),
				'default_value' => $this->get_first_field_by_type( 'name', 3 )
			),
			array(	
				'name'          => 'last_name',
				'label'         => esc_html__( 'Last Name', 'gravityformsicontact' ),
				'default_value' => $this->get_first_field_by_type( 'name', 6 )
			),
			array(	
				'name'          => 'suffix',
				'label'         => esc_html__( 'Suffix', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'street',
				'label'         => esc_html__( 'Address: Street Address', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'street2',
				'label'         => esc_html__( 'Address: Line 2', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'city',
				'label'         => esc_html__( 'Address: City', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'state',
				'label'         => esc_html__( 'Address: State', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'postal_code',
				'label'         => esc_html__( 'Address: Postal Code', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'phone',
				'label'         => esc_html__( 'Phone Number', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'fax',
				'label'         => esc_html__( 'Fax Number', 'gravityformsicontact' ),
			),
			array(	
				'name'          => 'business',
				'label'         => esc_html__( 'Business Number', 'gravityformsicontact' ),
			),
		);
		
	}

	/**
	 * Prepare custom fields for feed field mapping.
	 * 
	 * @access public
	 * @return array $fields
	 */
	public function custom_fields_for_feed_setting() {
		
		$fields = array();
		
		/* If iContact API credentials are invalid, return the fields array. */
		if ( ! $this->initialize_api() ) {
			return $fields;
		}
		
		/* Get available iContact fields. */
		$icontact_fields = $this->api->get_custom_fields();
		
		/* If no iContact fields exist, return the fields array. */
		if ( empty( $icontact_fields ) ) {
			return $fields;
		}
			
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
		
		if ( empty( $fields ) ) {
			return $fields;
		}
						
		/* Add "Add Custom Field" to array. */
		$fields[] = array(
			'label' => esc_html__( 'Add Custom Field', 'gravityformsicontact' ),
			'value' => 'gf_custom'	
		);
		
		return $fields;
		
	}

	/**
	 * Create new iContact custom fields.
	 * 
	 * @access public
	 * @param array $settings
	 * @return array $settings
	 */
	public function create_new_custom_fields( $settings ) {

		global $_gaddon_posted_settings;

		/* If no custom fields are set or if the API credentials are invalid, return settings. */
		if ( empty( $settings['custom_fields'] ) || ! $this->initialize_api() ) {
			return $settings;
		}
	
		/* Loop through each custom field. */
		foreach ( $settings['custom_fields'] as $index => &$field ) {
			
			/* If no custom key is set, move on. */
			if ( rgblank( $field['custom_key'] ) ) {
				continue;
			}
				
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

	/**
	 * Setup columns for feed list table.
	 * 
	 * @access public
	 * @return array
	 */
	public function feed_list_columns() {
		
		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformsicontact' ),
			'list'      => esc_html__( 'iContact List', 'gravityformsicontact' )
		);
		
	}
	
	/**
	 * Get value for list feed list column.
	 * 
	 * @access public
	 * @param array $feed
	 * @return string $list
	 */
	public function get_column_value_list( $feed ) {
			
		/* If iContact instance is not initialized, return list ID. */
		if ( ! $this->initialize_api() ) {
			return $feed['meta']['list'];
		}
		
		try {
			
			/* Get iContact list. */
			$list = $this->api->get_list( $feed['meta']['list'] );
			
			return $list['name'];
			
		} catch ( Exception $e ) {
			
			$this->log_error( __METHOD__ . '(): Unable to retrieve list; '. $e->getMessage() );			
		
			return $feed['meta']['list'];
		
		}
		
	}

	/**
	 * Set feed creation control.
	 * 
	 * @access public
	 * @return bool
	 */
	public function can_create_feed() {
		
		return $this->initialize_api() && $this->api->is_client_folder_set();
		
	}

	/**
	 * Enable feed duplication.
	 * 
	 * @access public
	 * @param int $feed_id
	 * @return bool
	 */
	public function can_duplicate_feed( $feed_id ) {
		
		return true;
		
	}

	/**
	 * Process feed.
	 * 
	 * @access public
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return void
	 */
	public function process_feed( $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Processing feed.' );
		
		/* If API instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {
			
			$this->add_feed_error( esc_html__( 'Feed was not processed because API was not initialized.', 'gravityformsicontact' ), $feed, $entry, $form );
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

		/* If the email address is empty, exit. */
		if ( GFCommon::is_invalid_or_empty_email( $contact['email'] ) ) {
			
			$this->add_feed_error( esc_html__( 'Contact could not be created as email address was not provided.', 'gravityformsicontact' ), $feed, $entry, $form );
			return;			
		
		}

		/* Add custom fields to contact array. */
		if ( rgars( $feed, 'meta/custom_fields' ) ) {
			
			foreach ( $feed['meta']['custom_fields'] as $custom_field ) {
				
				if ( rgblank( $custom_field['key'] ) || $custom_field['key'] == 'gf_custom' || rgblank( $custom_field['value'] ) ) {
					continue;
				}
	
				$field_value = $this->get_field_value( $form, $entry, $custom_field['value'] );
				
				if ( rgblank( $field_value ) ) {
					continue;
				}
					
				$contact[$custom_field['key']] = $field_value;
				
			}
			
		}
		
		/* Check to see if we're adding a new contact. */
		$find_contact = $this->api->get_contact_by_email( $contact['email'] );
		$is_new_contact = empty( $find_contact );
			
		if ( $is_new_contact ) {
			
			/* Log that we're creating a new contact. */
			$this->log_debug( __METHOD__ . "(): {$contact['email']} does not exist and will be created." );
			
			/* Log the contact object we're creating. */
			$this->log_debug( __METHOD__ . '(): Creating contact: ' . print_r( $contact, true ) );
			
			try {
				
				/* Add the contact. */
				$new_contact = $this->api->add_contact( $contact );

				/* Log that contact was created. */
				$this->log_debug( __METHOD__ . "(): {$contact['email']} has been created; contact ID: {$new_contact['contactId']}." );

			} catch ( Exception $e ) {
				
				/* Log error. */
				$this->add_feed_error( sprintf(
					esc_html__( 'Contact could not be created. %s', 'gravityformsicontact' ),
					$e->getMessage()
				), $feed, $entry, $form );
				
				/* Stop processing feed. */
				return false;
				
			}

			$contact['id'] = $new_contact['contactId'];
			$this->add_subscription( $contact, $feed, $entry, $form );
			
			
		} else {
			
			/* Log that we're updating an existing contact. */
			$this->log_debug( __METHOD__ . "(): {$contact['email']} already exists and will be updated." );

			/* Log the contact object we're updating. */
			$this->log_debug( __METHOD__ . '(): Updating contact: ' . print_r( $contact, true ) );

			$contact_id = $find_contact[0]['contactId'];

			try {
				
				/* Update the contact. */
				$update_contact = $this->api->update_contact( $contact_id, $contact );

				/* Log that contact was created. */
				$this->log_debug( __METHOD__ . "(): {$contact['email']} has been updated; contact ID: {$contact_id}." );

			} catch ( Exception $e ) {
				
				/* Log error. */
				$this->add_feed_error( sprintf(
					esc_html__( 'Contact could not be updated. %s', 'gravityformsicontact' ),
					$e->getMessage()
				), $feed, $entry, $form );
				
				/* Stop processing feed. */
				return false;
				
			}

			$contact['id'] = $contact_id;
			$this->add_subscription( $contact, $feed, $entry, $form );
			
		}

	}
	
	/**
	 * Add contact to subscription list.
	 * 
	 * @access public
	 * @param array $contact
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return bool
	 */
	public function add_subscription( $contact, $feed, $entry, $form ) {
		
		try {
			
			/* Subscribe the contact to the list. */
			$subscription = $this->api->add_contact_to_list( $contact['id'], $feed['meta']['list'] );

			/* Log whether or not contact was subscribed to list. */
			if ( empty ( $subscription ) ) {
				
				$this->log_debug( __METHOD__ . "(): {$contact['email']} was already subscribed to list." );
			
			} else {
				
				$this->log_debug( __METHOD__ . "(): {$contact['email']} has been subscribed to list; subscription ID: {$subscription[0]['subscriptionId']}." );
				
			}
			
		} catch ( Exception $e ) {
			
			/* Log error. */
			$this->add_feed_error( sprintf(
				esc_html__( 'Contact could not be subscribed to list. %s', 'gravityformsicontact' ),
				$e->getMessage()
			), $feed, $entry, $form );
			
			/* Stop processing feed. */
			return false;
			
		}
		
	}

	/**
	 * Initialized iContact API if credentials are valid.
	 * 
	 * @access public
	 * @return bool
	 */
	public function initialize_api() {

		if ( ! is_null( $this->api ) ) {
			return true;
		}
		
		/* Load the iContact API library. */
		require_once 'includes/class-icontact.php';

		/* Get the plugin settings */
		$settings = $this->get_plugin_settings();
		
		/* If any of the account information fields are empty, return null. */
		if ( rgblank( $settings['app_id'] ) || rgblank( $settings['api_username'] ) || rgblank( $settings['api_password'] ) ) {
			return null;
		}
			
		$this->log_debug( __METHOD__ . "(): Validating API info for {$settings['app_id']} / {$settings['api_username']}." );
		
		/* Create a new iContact object. */
		$icontact = new iContact( $settings['app_id'], $settings['api_username'], $settings['api_password'], rgar( $settings, 'client_folder' ) );
		
		try {
			
			/* Run a test request. */
			$contacts = $icontact->get_client_folders();
			
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
	
	/**
	 * Sets the default client folder is upgrading from pre-1.1.
	 * 
	 * @access public
	 * @param string $previous_version
	 * @return void
	 */
	public function upgrade( $previous_version ) {
		
		$previous_is_pre_client_folder_change = ! empty( $previous_version ) && version_compare( $previous_version, '1.1', '<' );
		
		if ( $previous_is_pre_client_folder_change ) {
			
			/* Initialize the API. */
			if ( ! $this->initialize_api() ) {
				return;
			}
			
			/* Get client folders. */
			try {
				
				$client_folders = $this->api->get_client_folders();
				
			} catch ( Exception $e ) {
				
				$this->log_error( __METHOD__ . '(): Unable to get client folders; ' . $e->getMessage() );
				
				return;
				
			}
			
			/* Get the plugin settings. */
			$settings = $this->get_plugin_settings();
			
			/* Add client folder to plugin settings. */
			$settings['client_folder'] = $client_folders[0]['clientFolderId'];
			
			/* Update plugin settings. */
			$this->update_plugin_settings( $settings );
			
		}
		
	}

}