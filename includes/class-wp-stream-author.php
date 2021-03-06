<?php

class MainWP_WP_Stream_Author {

	
	public $id;
	public $meta = array();
	protected $user;

	function __construct( $user_id, $author_meta = array() ) {
		$this->id   = $user_id;
		$this->meta = $author_meta;

		if ( $this->id ) {
			$this->user = new WP_User( $this->id );
		}
	}

	function __get( $name ) {
		if ( 'display_name' === $name ) {
			return $this->get_display_name();
		} elseif ( 'avatar_img' === $name ) {
			return $this->get_avatar_img();
		} elseif ( 'avatar_src' === $name ) {
			return $this->get_avatar_src();
		} elseif ( 'role' === $name ) {
			return $this->get_role();
		} elseif ( 'agent' === $name ) {
			return $this->get_agent();
		} elseif ( ! empty( $this->user ) && 0 !== $this->user->ID ) {
			return $this->user->$name;
		} else {
			throw new Exception( "Unrecognized magic '$name'" );
		}
	}

	function get_display_name() {
		if ( 0 === $this->id ) {
			if ( isset( $this->meta['system_user_name'] ) ) {
				return esc_html( $this->meta['system_user_name'] );
			}
			return esc_html__( 'N/A', 'mainwp-child-reports' );
		} else {
			if ( $this->is_deleted() ) {
				if ( ! empty( $this->meta['display_name'] ) ) {
					return $this->meta['display_name'];
				} elseif ( ! empty( $this->meta['user_login'] ) ) {
					return $this->meta['user_login'];
				} else {
					return esc_html__( 'N/A', 'mainwp-child-reports' );
				}
			} elseif ( ! empty( $this->user->display_name ) ) {
				return $this->user->display_name;
			} else {
				return $this->user->user_login;
			}
		}
	}

	function get_agent() {
		$agent = null;

		if ( ! empty( $this->meta['agent'] ) ) {
			$agent = $this->meta['agent'];
		} elseif ( ! empty( $this->meta['is_wp_cli'] ) ) {
			$agent = 'wp_cli'; // legacy
		}

		return $agent;
	}

	function get_avatar_img( $size = 80 ) {
		if ( ! get_option( 'show_avatars' ) ) {
			return false;
		}

		if ( 0 === $this->id ) {
			$url    = MAINWP_WP_STREAM_URL . 'ui/mainwp-reports-icons/wp-cli.png';
			$avatar = sprintf( '<img alt="%1$s" src="%2$s" class="avatar avatar-%3$s photo" height="%3$s" width="%3$s">', esc_attr( $this->get_display_name() ), esc_url( $url ), esc_attr( $size ) );
		} else {
			if ( $this->is_deleted() ) {
				$email  = $this->meta['user_email'];
				$avatar = get_avatar( $email, $size );
			} else {
				$avatar = get_avatar( $this->id, $size );
			}
		}

		return $avatar;
	}

	function get_avatar_src( $size = 80 ) {
		$img = $this->get_avatar_img( $size );

		if ( ! $img ) {
			return false;
		}

		if ( 1 === preg_match( '/src=([\'"])(.*?)\1/', $img, $matches ) ) {
			$src = html_entity_decode( $matches[2] );
		} else {
			return false;
		}

		return $src;
	}

	function get_role() {
		global $wp_roles;

		if ( ! empty( $this->meta['author_role'] ) && isset( $wp_roles->role_names[ $this->meta['author_role'] ] ) ) {
			$author_role = $wp_roles->role_names[ $this->meta['author_role'] ];
		} elseif ( ! empty( $this->meta['user_role_label'] ) ) {
			$author_role = $this->meta['user_role_label'];
		} elseif ( isset( $this->user->roles[0] ) && isset( $wp_roles->role_names[ $this->user->roles[0] ] ) ) {
			$author_role = $wp_roles->role_names[ $this->user->roles[0] ];
		} else {
			$author_role = null;
		}

		return $author_role;
	}

	function get_records_page_url() {
		$url = add_query_arg(
			array(
				'page'   => MainWP_WP_Stream_Admin::RECORDS_PAGE_SLUG,
				'author' => absint( $this->id ),
			),
			self_admin_url( MainWP_WP_Stream_Admin::ADMIN_PARENT_PAGE )
		);

		return $url;
	}

	function is_deleted() {
		return ( 0 !== $this->id && 0 === $this->user->ID );
	}

	function is_wp_cli() {
		return ( 'wp_cli' === $this->get_agent() );
	}

	function __toString() {
		return $this->get_display_name();
	}

	static function get_current_agent() {
		$agent = null;

		if ( defined( 'WP_CLI' ) ) {
			$agent = 'wp_cli';
		} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$agent = 'wp_cron';
		}

		$agent = apply_filters( 'mainwp_wp_stream_current_agent', $agent );

		return $agent;
	}

	static function get_agent_label( $agent ) {
		if ( 'wp_cli' === $agent ) {
			$label = esc_html__( 'via WP-CLI', 'mainwp-child-reports' );
		} elseif ( 'wp_cron' === $agent ) {
			$label = esc_html__( 'during WP Cron', 'mainwp-child-reports' );
		} else {
			$label = null;
		}

		$label = apply_filters( 'mainwp_wp_stream_agent_label', $label, $agent );

		return $label;
	}

}
