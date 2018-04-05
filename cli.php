<?php

/**
 * Manages login lockdowns of IP addresses.
 */
class Login_Lockdown_Command extends WP_CLI_Command {

	/**
	 * Locks down an IP address for a given username.
	 *
	 * ## OPTIONS
	 *
	 * <ip-address>
	 * : IP Address to lock down.
	 *
	 * <username>
	 * : Username to lock down.
	 *
	 * ## EXAMPLES
	 *
	 *     # Lock down an IP address
	 *     $ wp login-lockdown lockdown 127.0.0.1 admin
	 *     Success: IP Address locked down.
	 */
	function lockdown( $args, $assoc_args ) {
		$ip       = $args[0];
		$username = $args[1];

		// The `lockDown()` function uses the `$_SERVER['REMOTE_ADDR']` superglobal, so we set it here:
		$_SERVER['REMOTE_ADDR'] = $ip;

		// Lock down the IP:
		$result = lockDown( $username );

		// Unset the superglobal to avoid pollution.
		unset( $_SERVER['REMOTE_ADDR'] );

		if ( $result ) {
			WP_CLI::success( "IP address {$ip} locked down." );
		} else {
			WP_CLI::error( 'Invalid username.' );
		}
	}

	/**
	 * Is the IP address currently locked down?
	 *
	 * ## OPTIONS
	 *
	 * <ip-address>
	 * : IP Address to check.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check an IP address
	 *     $ wp login-lockdown is-locked-down 127.0.0.1
	 *     Error: IP Address 127.0.0.1 is locked down
	 *
	 *     # Check an IP address
	 *     $ wp login-lockdown is-locked-down 1.2.3.4
	 *     Success: IP Address 1.2.3.4 is not locked down
	 *
	 * @subcommand is-locked-down
	 */
	function is_locked_down( $args, $assoc_args ) {
		$ip = $args[0];

		// The `isLockedDown()` function uses the `$_SERVER['REMOTE_ADDR']` superglobal, so we set it here:
		$_SERVER['REMOTE_ADDR'] = $ip;

		// Check the lockdown status:
		$result = isLockedDown();

		// Unset the superglobal to avoid pollution.
		unset( $_SERVER['REMOTE_ADDR'] );

		if ( $result ) {
			WP_CLI::error( "IP address {$ip} is locked down." );
		} else {
			WP_CLI::success( "IP address {$ip} is not locked down." );
		}
	}

	/**
	 * Lists the currently locked down IP address for a given username.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List the locked down IP addresses
	 *     $ wp login-lockdown list
	 *     +-------------+--------------+-------------+
	 *     | lockdown_ID | minutes_left | lockdown_IP |
	 *     +-------------+--------------+-------------+
	 *     | 17          | 37           | 1.2.3.4     |
	 *     +-------------+--------------+-------------+
	 *
	 * @subcommand list
	 */
	function _list( $args, $assoc_args ) {
		// Set some default associative argument values:
		$assoc_args = array_merge( array(
			'format' => 'table',
		), $assoc_args );

		// Fetch the list of lockdowns:
		$list = listLockedDown();

		// Format the output according to the --format parameter or its default value:
		WP_CLI\Utils\format_items( $assoc_args['format'], $list, array(
			'lockdown_ID',
			'minutes_left',
			'lockdown_IP',
		) );
	}

	/**
	 * Release a locked down IP address
	 *
	 * ## OPTIONS
	 *
	 * <ip-address>
	 * : IP Address to release.
	 *
	 * ## EXAMPLES
	 *
	 *     # Release an IP address
	 *     $ wp login-lockdown release 127.0.0.1
	 *     Success: IP Address 127.0.0.1 released.
	 *
	 */
	function release( $args, $assoc_args ) {
		global $wpdb;

		$ip = $args[0];

		// The Login Lockdown plugin doesn't provide a function to release an IP address (the code is buried
		// inside the `print_loginlockdownAdminPage()` function) so we need to perform an SQL query instead.

		// Fetch the ID of the row in the lockdown table:
		$query = $wpdb->prepare( "
			SELECT lockdown_ID
			FROM {$wpdb->prefix}lockdowns
			WHERE lockdown_IP = %s
		", $ip );
		$id = $wpdb->get_var( $query );

		if ( empty( $id ) ) {
			WP_CLI::error( "IP address {$ip} is not locked down." );
		}

		// Release the IP address by updating its `release_date` field:
		$releasequery = $wpdb->prepare( "
			UPDATE {$wpdb->prefix}lockdowns
			SET release_date = now()
			WHERE lockdown_ID = %d
		", $id );
		$result = $wpdb->query( $releasequery );

		if ( false === $result ) {
			WP_CLI::error( "Could not release IP address {$ip}." );
		}

		WP_CLI::success( "IP address {$ip} released." );
	}

	/**
	 * Update a Login Lockdown configuration setting
	 *
	 * ## OPTIONS
	 *
	 * <setting-name>
	 * : The Login Lockdown setting name
	 *
	 * <setting-value>
	 * : The Login Lockdown setting value
	 *
	 * ## EXAMPLES
	 *
	 *     # Update the max_login_retries setting
	 *     $ wp login-lockdown update-setting max_login_retries 5
	 *     Success: Setting updated.
	 *
	 * @subcommand update-setting
	 */
	function update_setting( $args, $assoc_args ) {
		// Login Lockdown stores its settings inside a serialized option.
		// Maybe this command could fetch the `loginlockdownAdminOptions` option, modify the
		// corresponding field, and update the option in the database?...

		WP_CLI::error( 'This command has not been implemented yet.' );
	}

}

WP_CLI::add_command( 'login-lockdown', 'Login_Lockdown_Command' );
