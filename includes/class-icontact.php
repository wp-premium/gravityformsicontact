<?php
	
	class iContact {
		
		protected $api_url = 'https://app.icontact.com/icp/a/';
		protected $account_id = null;
		protected $client_folder_id = null;
		
		public function __construct( $app_id, $api_username, $api_password, $client_folder_id = null ) {
			
			$this->app_id = $app_id;
			$this->api_username = $api_username;
			$this->api_password = $api_password;
			$this->client_folder_id = $client_folder_id;
			
		}
		
		/**
		 * Get base path of API requests.
		 * 
		 * @access public
		 * @return void
		 */
		public function get_url_base() {
			
			return $this->set_account_id() .'/c/'. $this->client_folder_id .'/';
			
		}
		
		/**
		 * Get array of headers needed for every API request.
		 * 
		 * @access public
		 * @return void
		 */
		public function request_headers() {
			
			return array(
				'Expect'       => '',
				'Accept'       => 'application/json',
				'Content-type' => 'application/json',
				'Api-Version'  => '2.2',
				'Api-AppId'    => $this->app_id,
				'Api-Username' => $this->api_username,
				'Api-Password' => $this->api_password
			);
			
		}

		/**
		 * Make API request.
		 * 
		 * @access public
		 * @param string $action
		 * @param array $options (default: array())
		 * @param string $method (default: 'GET')
		 * @return void
		 */
		public function make_request( $action = null, $options = array(), $method = 'GET', $return_key = null ) {

			/**
			 * Filter the options array so that is modifiable before sending requests to iContact.
			 *
			 * @since 1.2.1
			 * @see   https://www.gravityhelp.com/documentation/article/gform_icontact_request_args/
			 *
			 * @param array  $options    Query arguments to be sent in the request.
			 * @param string $action     The action being sent to the iContact API, passed in the URL.
			 * @param string $method     The request method being used. Example: GET.
			 * @param string $return_key The array key desired from the response.
			 */
			$options = apply_filters( 'gform_icontact_request_args', $options, $action, $method, $return_key );

			// Build request options string.
			$request_options = ( $method == 'GET' && ! empty( $options ) ) ? '?' . http_build_query( $options ) : '';
			
			// Build request URL.
			$request_url = $this->api_url . $action . $request_options;
			
			// Prepare request and execute.
			$args = array(
				'headers' => $this->request_headers(),
				'method'  => $method
			);
			
			if ( $method == 'POST' ) {
				$args['body'] = json_encode( $options );
			}

			$response = wp_remote_request( $request_url, $args );
			
			// If WP_Error, die. Otherwise, return decoded JSON.
			if ( is_wp_error( $response ) ) {
				
				die( 'Request failed. '. $response->get_error_message() );
				
			} else {
				
				$response = json_decode( $response['body'], true );
				
				if ( isset( $response['errors'] ) ) {
					throw new Exception( $response['errors'][0] );
				}

				if ( isset( $response['warnings'] ) ) {
					throw new Exception( $response['warnings'][0] );
				}
				
				return empty( $return_key ) ? $response : $response[$return_key];	
				
			}
			
		}
		
		/**
		 * Fetch the Account ID.
		 * 
		 * @access public
		 * @return void
		 */
		public function set_account_id() {
			
			if ( empty( $this->account_id ) ) {
				
				$accounts = $this->make_request();
				
				if ( isset( $accounts['errors'] ) )
					throw new Exception( $accounts['errors'][0] );
				
				$account = $accounts['accounts'][0];
				
				if ( $account['enabled'] == 1 ) {
					
					$this->account_id = $account['accountId'];
					
				} else {
					
					throw new Exception( 'Your account has been disabled.' );
					
				}
			
			}
			
			return $this->account_id;
			
		}
				
		/**
		 * Add a new contact.
		 * 
		 * @access public
		 * @param array $contact
		 * @return array
		 */
		public function add_contact( $contact ) {
			
			$contacts = $this->make_request( $this->get_url_base() . 'contacts', array( $contact ), 'POST', 'contacts' );
			
			return $contacts[0];
			
		}
		
		/**
		 * Add a contact to a list.
		 * 
		 * @access public
		 * @param int $contact_id
		 * @param int $list_id
		 * @param string $status (default: 'normal')
		 * @return void
		 */
		public function add_contact_to_list( $contact_id, $list_id, $status = 'normal' ) {
			
			$subscription = array(
				'contactId' => $contact_id,
				'listId'    => $list_id,
				'status'    => $status
			);
			
			$new_subscription = $this->make_request( $this->get_url_base() . 'subscriptions', array( $subscription ), 'POST', 'subscriptions' );
			
			return $new_subscription;
			
		}
		
		/**
		 * Add new custom field to account.
		 * 
		 * @access public
		 * @param mixed $custom_field
		 * @return void
		 */
		public function add_custom_field( $custom_field ) {
			
			return $this->make_request( $this->get_url_base() . 'customfields', array( $custom_field ), 'POST', 'customfields' );
		
		}
		
		/**
		 * Get available client folders.
		 * 
		 * @access public
		 * @return array $folders
		 */
		public function get_client_folders() {
			
			/* If the account ID isn't set, go set it. */
			if ( empty( $this->account_id ) ) {
				$this->set_account_id();
			}
				
			$clients = $this->make_request( $this->account_id . '/c/', array( 'limit' => 999 ) );
			
			if ( isset( $clients['errors'] ) ) {
				throw new Exception( 'No client folders were found for this account.' );
			}	
			
			return $clients['clientfolders'];
			
		}

		/**
		 * Fetch all contacts associated with this account.
		 * 
		 * @access public
		 * @return void
		 */
		public function get_contacts() {
			
			return $this->make_request( $this->get_url_base() . 'contacts', array(), 'GET', 'contacts' );
			
		}
		
		/**
		 * Fetch contact by email address.
		 * 
		 * @access public
		 * @return void
		 */
		public function get_contact_by_email( $email ) {
			
			return $this->make_request( $this->get_url_base() . 'contacts', array( 'email' => $email ), 'GET', 'contacts' );
			
		}
	
		/**
		 * Fetch custom fields for associated with this account.
		 * 
		 * @access public
		 * @return void
		 */
		public function get_custom_fields() {
			
			return $this->make_request( $this->get_url_base() . 'customfields', array(), 'GET', 'customfields' );
			
		}
	
		/**
		 * Fetch all lists associated with this account.
		 * 
		 * @access public
		 * @return void
		 */
		public function get_lists() {
			
			return $this->make_request( $this->get_url_base() . 'lists', array( 'limit' => 999 ), 'GET', 'lists' );
			
		}

		/**
		 * Fetch a specific list associated with this account.
		 * 
		 * @access public
		 * @param mixed $list_id
		 * @return void
		 */
		public function get_list( $list_id ) {
			
			return $this->make_request( $this->get_url_base() . 'lists/' . $list_id, array(), 'GET', 'list' );
			
		}

		/**
		 * Checks to see if a client folder has been selected.
		 * 
		 * @access public
		 * @return bool
		 */
		public function is_client_folder_set() {
			
			return ! empty( $this->client_folder_id );
			
		}

		/**
		 * Update an existing contact.
		 * 
		 * @access public
		 * @param int $contact_id
		 * @param array $contact
		 * @return void
		 */
		public function update_contact( $contact_id, $contact ) {
			
			return $this->make_request( $this->get_url_base() . 'contacts/'. $contact_id, $contact, 'POST', 'contact' );
			
		}
	
	}