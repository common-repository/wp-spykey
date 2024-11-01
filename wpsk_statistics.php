<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( !current_user_can( "administrator" ) ) { exit; }
?>

<script type="text/javascript">
	var users_offset = 50;
	var export_url = '<?php echo plugin_dir_url( __FILE__ ); ?>exports/tracked_users.csv';
</script>

<?php
$tracker_ = new WP_SPYKEY;
parse_str( $_SERVER[ "QUERY_STRING" ] );
if ( !isset( $unregistered ) ) { $unregistered = false; }
?>

<div class="wrap">
	<h1>WP Spykey - Statistics</h1>

	<?php if ( !$unregistered ) { ?>
	<h2>Registered visitors</h2>
	<?php } else { ?>
	<h2>Unregistered visitors</h2>
	<?php } ?>

	<div id="menu">
		<a href="<?php echo get_admin_url( null, 'options-general.php?page=wpsk-statistics' ); ?>" class="button action">View Registered</a>
		<a href="<?php echo get_admin_url( null, 'options-general.php?page=wpsk-statistics&unregistered=true' ); ?>" class="button action">View Unregistered</a>
	</div>

	<div id="list-holder">
		<?php if ( !$unregistered ) { ?>
		<table id="users-container" class="users-list">
			<tr>
				<th>User ID</th>
				<th>User Email</th>
				<th>Last Login</th>
				<th>User City</th>
				<th>User Country</th>
				<th>Page Visited</th>
				<th>Actions</th>
			</tr>
			<?php
			$args = array(
					'meta_key' => 'user_last_login',
					'meta_value' => '',
					'meta_compare' => '!=',
					'orderby' => 'meta_value',
					'order' => 'DESC',
					'number' => '50'
				);
			$users_ = get_users( $args );

			foreach ( $users_ as $user_ ) {
				$user_id = $user_->data->ID;
				$user_email = $user_->data->user_email;
				$user_last_login = get_user_meta( $user_id, "user_last_login", true );
				$user_meta = $tracker_->get_user_information( $user_id, 1 );

				if ( !empty( $user_meta ) ) {
					$user_meta = $user_meta[ 0 ];
					$user_meta_details = $tracker_->get_user_details( $user_meta->user_ip );
					$row_id = $user_meta->ID;

					$user_browser = false;
					if ( get_cfg_var( "browscap" ) ) { $user_browser = get_browser( $user_meta->user_agent ); }
					?>

					<tr>
						<td><?php echo $user_id; ?></td>
						<td><?php echo $user_email; ?></td>
						<td><?php echo $user_last_login; ?></td>
						<td><?php echo $user_meta->user_city; ?></td>
						<td><?php echo $user_meta->user_country; ?></td>
						<td><?php echo $user_meta->page_id > 0 ? get_the_title( $user_meta->page_id ) : "Unknown page"; ?></td>
						<td>
						<?php
						if ( $user_meta->lat_long != "Unknown" ) {
							echo "<a href='http://maps.google.com/maps?q={$user_meta->lat_long}' target='_blank' class='button button-primary button-large'>Map</a>";
						}
						?>
							<button id="more-details-controller" class="button action" onclick='showHideDetails(<?php echo $row_id; ?>);'>Details</button>

							<div id="details-<?php echo $row_id; ?>" class='details-container'>
								<p><strong>IP:</strong> <?php echo $user_meta->user_ip; ?></p>
								<p><strong>Last Visit:</strong> <?php echo $user_meta->last_visit; ?></p>
								<p><strong>Hostname:</strong> <?php echo !empty( $user_meta_details->hostname ) ? $user_meta_details->hostname : "Unknown"; ?></p>
								<p><strong>Organisation:</strong> <?php echo !empty( $user_meta_details->org ) ? $user_meta_details->org : "Unknown"; ?></p>
								<p><strong>Region:</strong> <?php echo !empty( $user_meta_details->region ) ? $user_meta_details->region : "Unknown"; ?></p>
								<p><strong>Phone code:</strong> <?php echo !empty( $user_meta_details->phone ) ? $user_meta_details->phone : "Unknown"; ?></p>
								<?php if ( $user_browser != false ) { ?>
								<p><strong>Browser:</strong> <?php echo $user_browser->browser; ?></p>
								<p><strong>Browser Version:</strong> <?php echo $user_browser->version; ?></p>
								<p><strong>Platform/OS:</strong> <?php echo ucfirst( $user_browser->platform ); ?></p>
								<?php } ?>
							</div>
						</td>
					</tr>

					<?php
				}
			}
			?>
		</table>
		<?php if ( !empty( $user_meta ) ) { ?><button id='load-more-button' list-needed='registered' class="button button-primary">Load more</button><?php }
		} else { ?>
			<table id="users-container" class="users-list">
				<tr>
					<th>User IP</th>
					<th>Last Visit</th>
					<th>User City</th>
					<th>User Region</th>
					<th>User Country</th>
					<th>Page Visited</th>
					<th>Actions</th>
				</tr>
				<?php
				$users_ = $tracker_->get_unregistered_users();

				foreach ( $users_ as $user_meta ) {
					if ( !empty( $user_meta ) ) {
						$user_meta_details = $tracker_->get_user_details( $user_meta->user_ip );
						$row_id = $user_meta->ID;

						$user_browser = false;
						if ( get_cfg_var( "browscap" ) ) { $user_browser = get_browser( $user_meta->user_agent ); }
						?>

						<tr>
							<td><?php echo $user_meta->user_ip; ?></td>
							<td><?php echo $user_meta->last_visit; ?></td>
							<td><?php echo $user_meta->user_city; ?></td>
							<td><?php echo !empty( $user_meta_details->region ) ? $user_meta_details->region : "Unknown"; ?></td>
							<td><?php echo $user_meta->user_country; ?></td>
							<td><?php echo $user_meta->page_id > 0 ? get_the_title( $user_meta->page_id ) : "Unknown page"; ?></td>
							<td>
							<?php
							if ( $user_meta->lat_long != "Unknown" ) {
								echo "<a href='http://maps.google.com/maps?q={$user_meta->lat_long}' target='_blank' class='button button-primary button-large'>Map</a>";
							}
							?>
								<button id="more-details-controller" class="button action" onclick='showHideDetails(<?php echo $row_id; ?>);'>Details</button>

								<div id="details-<?php echo $row_id; ?>" class='details-container'>
									<p><strong>Hostname:</strong> <?php echo !empty( $user_meta_details->hostname ) ? $user_meta_details->hostname : "Unknown"; ?></p>
									<p><strong>Organisation:</strong> <?php echo !empty( $user_meta_details->org ) ? $user_meta_details->org : "Unknown"; ?></p>
									<p><strong>Phone code:</strong> <?php echo !empty( $user_meta_details->phone ) ? $user_meta_details->phone : "Unknown"; ?></p>
									<?php if ( $user_browser != false ) { ?>
									<p><strong>Browser:</strong> <?php echo $user_browser->browser; ?></p>
									<p><strong>Browser Version:</strong> <?php echo $user_browser->version; ?></p>
									<p><strong>Platform/OS:</strong> <?php echo ucfirst( $user_browser->platform ); ?></p>
									<?php } ?>
								</div>
							</td>
						</tr>

						<?php
					}
				}
				?>
			</table>
			<?php if ( !empty( $user_meta ) ) { ?><button id='load-more-button' list-needed='unregistered' class="button button-primary">Load more</button><?php }
		} ?>
	</div>
</div>
