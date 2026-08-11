<?php
/**
 * Plugin Name: Exam Result Manager
 * Plugin URI: https://github.com/bungakku/Exam-Result-Search-PlugIn
 * Description: Exam Results Manager with detailed subject marks and printable function.
 * Version: 4.7.5
 * Author: Biswajit Thokchom
 * Author URI: https://github.com/bungakku
 * Text Domain: exam-result-manager
 * GitHub Plugin URI: https://github.com/bungakku/Exam-Result-Search-PlugIn
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ERM_VERSION', '4.7.5' );
define( 'ERM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ERM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ERM_GITHUB_REPO', 'bungakku/Exam-Result-Search-PlugIn' );

/**
 * GitHub Updater – enables auto‑update notifications from GitHub releases.
 */
class ERM_GitHub_Updater {
    private $plugin_file;
    private $plugin_slug;   // basename, e.g. exam-result-manager/exam_result_search.php
    private $slug;          // folder slug only, e.g. exam-result-manager
    private $github_repo;
    private $error_option;
    private $last_error = '';

    public function __construct( $plugin_file, $github_repo ) {
        $this->plugin_file  = $plugin_file;
        $this->plugin_slug  = plugin_basename( $plugin_file );
        $this->slug         = dirname( $this->plugin_slug );
        $this->github_repo  = $github_repo;
        $this->error_option = 'erm_github_update_error';

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
        add_action( 'in_plugin_update_message-' . $this->plugin_slug, array( $this, 'update_message' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'maybe_show_error_notice' ) );
        add_filter( 'plugin_action_links_' . $this->plugin_slug, array( $this, 'add_check_update_link' ) );
        add_action( 'admin_init', array( $this, 'maybe_handle_manual_check' ) );
    }

    /**
     * Hooked into WordPress's own update-check transient (refreshed on its normal
     * schedule, or immediately after "Check Again" on the Updates screen / our
     * manual check link below). No separate plugin-level cache layer here —
     * that was the previous source of stale "no update available" results
     * surviving past an actual new release.
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if ( ! $release || ! isset( $release->tag_name ) ) {
            // Don't silently say nothing -- remember why, so the admin can see it.
            update_option( $this->error_option, $this->last_error ? $this->last_error : __( 'Could not reach the GitHub releases API.', 'exam-result-manager' ) );
            return $transient;
        }

        delete_option( $this->error_option );

        $latest_version = ltrim( $release->tag_name, 'v' );
        if ( version_compare( $latest_version, ERM_VERSION, '>' ) ) {
            $update_data = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $this->plugin_slug,
                'new_version' => $latest_version,
                'package'     => $release->zipball_url,
                'url'         => 'https://github.com/' . $this->github_repo,
                'tested'      => '6.7',
            );
            $transient->response[ $this->plugin_slug ] = $update_data;
        } else {
            // Explicitly mark as "no update" so the Updates screen doesn't
            // keep treating it as unchecked.
            unset( $transient->response[ $this->plugin_slug ] );
            $transient->no_update[ $this->plugin_slug ] = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $this->plugin_slug,
                'new_version' => ERM_VERSION,
                'url'         => 'https://github.com/' . $this->github_repo,
            );
        }

        return $transient;
    }

    private function get_latest_release() {
        $url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array( 'Accept' => 'application/vnd.github+json' ),
        ) );

        if ( is_wp_error( $response ) ) {
            $this->last_error = sprintf(
                /* translators: %s: error message */
                __( 'GitHub update check failed: %s', 'exam-result-manager' ),
                $response->get_error_message()
            );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            $this->last_error = sprintf(
                /* translators: %1$s: repo slug, %2$d: HTTP status code */
                __( 'GitHub returned an unexpected response for %1$s (HTTP %2$d). Check that the repository exists and has at least one published release.', 'exam-result-manager' ),
                $this->github_repo,
                (int) $code
            );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( ! isset( $data->tag_name ) ) {
            $this->last_error = __( 'GitHub API response did not contain a release tag. Make sure a release (not just a tag) has been published.', 'exam-result-manager' );
            return null;
        }

        return $data;
    }

    public function plugin_info( $false, $action, $args ) {
        if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== $this->slug ) {
            return $false;
        }
        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $false;
        }

        $info = (object) array(
            'name'          => 'Exam Result Manager',
            'slug'          => $this->slug,
            'version'       => ltrim( $release->tag_name, 'v' ),
            'author'        => 'Biswajit Thokchom',
            'author_profile' => 'https://github.com/bungakku',
            'last_updated'  => $release->published_at,
            'homepage'      => 'https://github.com/' . $this->github_repo,
            'download_link' => $release->zipball_url,
            'sections'      => array(
                'description' => 'This plugin manages student exam results with subject-wise marks, grade calculation, CSV import, and printable marksheets.',
                'changelog'   => $this->get_changelog_from_release( $release ),
            ),
        );
        return $info;
    }

    private function get_changelog_from_release( $release ) {
        if ( ! empty( $release->body ) ) {
            return nl2br( esc_html( $release->body ) );
        }
        return 'See the <a href="https://github.com/' . esc_attr( $this->github_repo ) . '/releases">release notes</a> on GitHub.';
    }

    public function update_message( $plugin_data, $response ) {
        if ( ! empty( $response->package ) ) {
            echo ' <em>' . __( 'Update available from GitHub.', 'exam-result-manager' ) . '</em>';
        }
    }

    // Adds a "Check for updates" link on the Plugins list row.
    public function add_check_update_link( $links ) {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return $links;
        }
        $url = wp_nonce_url(
            add_query_arg( array( 'erm_check_update' => '1' ), admin_url( 'plugins.php' ) ),
            'erm_check_update'
        );
        $links['erm-check-update'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'exam-result-manager' ) . '</a>';
        return $links;
    }

    // Handles the manual "Check for updates" click: clears WP's own update
    // transient so the very next admin page load re-runs check_for_update().
    public function maybe_handle_manual_check() {
        if ( ! isset( $_GET['erm_check_update'] ) || ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'erm_check_update' ) ) {
            return;
        }
        delete_site_transient( 'update_plugins' );
        wp_redirect( remove_query_arg( array( 'erm_check_update', '_wpnonce' ) ) );
        exit;
    }

    // Surfaces the last GitHub fetch error (if any) on the Plugins screen,
    // instead of silently behaving as if everything is up to date.
    public function maybe_show_error_notice() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        $error = get_option( $this->error_option );
        if ( ! $error ) {
            return;
        }
        echo '<div class="notice notice-warning is-dismissible"><p>' .
            '<strong>' . esc_html__( 'Exam Result Manager update check:', 'exam-result-manager' ) . '</strong> ' .
            esc_html( $error ) .
            '</p></div>';
    }
}

// Initialize updater
new ERM_GitHub_Updater( __FILE__, ERM_GITHUB_REPO );


// --------------------------------------------------------------------------

class ExamResultManager {

    public function __construct() {
        add_action( 'init', array( $this, 'register_result_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_result_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_result_data' ), 10, 2 );
        add_shortcode( 'exam_result_search', array( $this, 'render_result_search' ) );
        add_action( 'admin_menu', array( $this, 'add_manager_page' ) );
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_import_exam_results', array( $this, 'handle_csv_import' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_print_marksheet', array( $this, 'ajax_print_marksheet' ) );
        add_action( 'wp_ajax_nopriv_print_marksheet', array( $this, 'ajax_print_marksheet' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        load_plugin_textdomain( 'exam-result-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    // Register custom post type
    public function register_result_post_type() {
        register_post_type( 'exam_result', array(
            'labels' => array(
                'name'               => __( 'Exam Results', 'exam-result-manager' ),
                'singular_name'      => __( 'Exam Result', 'exam-result-manager' ),
                'add_new'            => __( 'Add New Result', 'exam-result-manager' ),
                'add_new_item'       => __( 'Add New Exam Result', 'exam-result-manager' ),
                'edit_item'          => __( 'Edit Exam Result', 'exam-result-manager' ),
                'all_items'          => __( 'All Exam Results', 'exam-result-manager' ),
            ),
            'public'             => true,
            'has_archive'        => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-welcome-learn-more',
            'supports'           => array( 'title' ),
            'capability_type'    => 'post',
            'publicly_queryable' => false,
            'exclude_from_search'=> true,
        ) );
    }

    // Add meta boxes
    public function add_result_meta_box() {
        add_meta_box(
            'exam_result_details',
            __( 'Student Details', 'exam-result-manager' ),
            array( $this, 'render_result_meta_box' ),
            'exam_result',
            'normal',
            'default'
        );
        add_meta_box(
            'exam_subject_marks',
            __( 'Subject-wise Detailed Marks', 'exam-result-manager' ),
            array( $this, 'render_subject_marks_meta_box' ),
            'exam_result',
            'normal',
            'default'
        );
    }

    public function render_result_meta_box( $post ) {
        wp_nonce_field( 'exam_result_nonce', 'exam_result_nonce_field' );
        $fields = array(
            'student_name'     => __( 'Name', 'exam-result-manager' ),
            'student_class'    => __( 'Class', 'exam-result-manager' ),
            'student_section'  => __( 'Section', 'exam-result-manager' ),
            'student_rollno'   => __( 'Roll No', 'exam-result-manager' ),
            'student_semester' => __( 'Semester', 'exam-result-manager' ),
            'exam_year'        => __( 'Exam Year (e.g., 2024-2025)', 'exam-result-manager' ),
        );
        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, "_$key", true );
            echo "<p><label><strong>$label:</strong></label><br>";
            echo "<input type='text' name='$key' value='" . esc_attr( $value ) . "' style='width:100%;'></p>";
        }
    }

    // Detailed subject meta box
    public function render_subject_marks_meta_box( $post ) {
        $subjects = get_post_meta( $post->ID, '_detailed_subjects', true );
        if ( ! is_array( $subjects ) ) {
            $subjects = array();
        }
        $old_style = get_post_meta( $post->ID, '_subject_marks', true );
        if ( ! empty( $old_style ) && empty( $subjects ) ) {
            echo '<div class="notice notice-warning"><p>' . __( 'This result uses the old simple format. Please edit and save to convert to detailed format.', 'exam-result-manager' ) . '</p></div>';
        }

        $max_internal = get_option( 'exam_max_internal', 10 );
        $max_external = get_option( 'exam_max_external', 70 );
        $max_practical = get_option( 'exam_max_practical', 20 );

        echo '<div id="detailed-subjects-container">';
        if ( empty( $subjects ) ) {
            echo $this->get_detailed_subject_row_html();
        } else {
            foreach ( $subjects as $index => $subj ) {
                echo $this->get_detailed_subject_row_html(
                    $subj['code'],
                    $subj['name'],
                    $subj['internal'],
                    $subj['external'],
                    $subj['practical']
                );
            }
        }
        echo '</div>';
        echo '<button type="button" id="add-detailed-subject" class="button">' . __( 'Add Subject', 'exam-result-manager' ) . '</button>';
        echo '<p><em>' . sprintf( __( 'Max marks: Internal %d, External %d, Practical %d. You can change these in <a href="%s">Marksheet Settings</a>.', 'exam-result-manager' ), $max_internal, $max_external, $max_practical, admin_url( 'edit.php?post_type=exam_result&page=exam-result-settings' ) ) . '</em></p>';

        // Pass max marks to JS safely
        $max_marks = array(
            'internal'  => intval( $max_internal ),
            'external'  => intval( $max_external ),
            'practical' => intval( $max_practical ),
        );
        wp_localize_script( 'jquery', 'ermMaxMarks', $max_marks );

        $row_template = $this->get_detailed_subject_row_html();
        ?>
        <script>
        jQuery(document).ready(function($) {
            var rowTemplate = <?php echo wp_json_encode( $row_template ); ?>;

            $("#add-detailed-subject").click(function() {
                $("#detailed-subjects-container").append(rowTemplate);
                recalcAllTotals();
            });

            $(document).on("click", ".remove-detailed-subject", function() {
                $(this).closest(".detailed-subject-row").remove();
                recalcAllTotals();
            });

            $(document).on("keyup change", ".internal-marks, .external-marks, .practical-marks", function() {
                var row = $(this).closest(".detailed-subject-row");
                var internal = parseFloat(row.find(".internal-marks").val()) || 0;
                var external = parseFloat(row.find(".external-marks").val()) || 0;
                var practical = parseFloat(row.find(".practical-marks").val()) || 0;
                var total = internal + external + practical;
                row.find(".subject-total").val(total);
                var maxInternal = <?php echo intval( $max_internal ); ?>;
                var maxExternal = <?php echo intval( $max_external ); ?>;
                var maxPractical = <?php echo intval( $max_practical ); ?>;
                var maxTotal = maxInternal + maxExternal + maxPractical;
                var percentage = maxTotal > 0 ? (total / maxTotal) * 100 : 0;
                var grade = getGrade(percentage);
                row.find(".subject-grade").val(grade);
                recalcAllTotals();
            });

            function getGrade(percentage) {
                if (percentage >= 90) return 'A+';
                if (percentage >= 80) return 'A';
                if (percentage >= 70) return 'B+';
                if (percentage >= 60) return 'B';
                if (percentage >= 50) return 'C';
                if (percentage >= 40) return 'D';
                return 'F';
            }

            function recalcAllTotals() {
                var overallTotal = 0;
                var maxOverall = 0;
                $(".detailed-subject-row").each(function() {
                    var total = parseFloat($(this).find(".subject-total").val()) || 0;
                    overallTotal += total;
                    var maxInternal = <?php echo intval( $max_internal ); ?>;
                    var maxExternal = <?php echo intval( $max_external ); ?>;
                    var maxPractical = <?php echo intval( $max_practical ); ?>;
                    maxOverall += (maxInternal + maxExternal + maxPractical);
                });
                $("#overall-total-display").text(overallTotal);
                $("#overall-max-display").text(maxOverall);
                var overallPercentage = maxOverall > 0 ? (overallTotal / maxOverall) * 100 : 0;
                var overallGrade = getGrade(overallPercentage);
                $("#overall-grade-display").text(overallGrade);
                $("#overall-total-input").val(overallTotal);
                $("#overall-grade-input").val(overallGrade);
            }
            recalcAllTotals();
        });
        </script>
        <?php
        $overall_total = get_post_meta( $post->ID, '_student_marks', true );
        $overall_grade = get_post_meta( $post->ID, '_student_grade', true );
        echo '<input type="hidden" id="overall-total-input" name="overall_total" value="' . esc_attr( $overall_total ) . '">';
        echo '<input type="hidden" id="overall-grade-input" name="overall_grade" value="' . esc_attr( $overall_grade ) . '">';
        echo '<div style="margin-top:15px; padding:10px; background:#f0f0f0;"><strong>' . __( 'Overall Total:', 'exam-result-manager' ) . '</strong> <span id="overall-total-display">' . esc_html( $overall_total ) . '</span> | <strong>' . __( 'Overall Grade:', 'exam-result-manager' ) . '</strong> <span id="overall-grade-display">' . esc_html( $overall_grade ) . '</span></div>';
    }

    private function get_detailed_subject_row_html( $code = '', $name = '', $internal = '', $external = '', $practical = '' ) {
        $max_internal = get_option( 'exam_max_internal', 10 );
        $max_external = get_option( 'exam_max_external', 70 );
        $max_practical = get_option( 'exam_max_practical', 20 );
        $total = floatval( $internal ) + floatval( $external ) + floatval( $practical );
        $max_total = $max_internal + $max_external + $max_practical;
        $percentage = $max_total > 0 ? ( $total / $max_total ) * 100 : 0;
        $grade = $this->calculate_subject_grade( $percentage );

        return '<div class="detailed-subject-row" style="margin-bottom:15px; padding:10px; border:1px solid #ddd; background:#fafafa;">'
             . '<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">'
             . '<input type="text" name="subject_code[]" placeholder="' . esc_attr__( 'Code', 'exam-result-manager' ) . '" value="' . esc_attr( $code ) . '" style="width:80px;">'
             . '<input type="text" name="subject_name[]" placeholder="' . esc_attr__( 'Subject Name', 'exam-result-manager' ) . '" value="' . esc_attr( $name ) . '" style="width:150px;">'
             . '<input type="number" name="subject_internal[]" class="internal-marks" placeholder="Int (' . esc_attr( $max_internal ) . ')" value="' . esc_attr( $internal ) . '" style="width:80px;" step="0.01">'
             . '<input type="number" name="subject_external[]" class="external-marks" placeholder="Ext (' . esc_attr( $max_external ) . ')" value="' . esc_attr( $external ) . '" style="width:80px;" step="0.01">'
             . '<input type="number" name="subject_practical[]" class="practical-marks" placeholder="Prac (' . esc_attr( $max_practical ) . ')" value="' . esc_attr( $practical ) . '" style="width:80px;" step="0.01">'
             . '<input type="text" name="subject_total[]" class="subject-total" placeholder="Total" value="' . esc_attr( $total ) . '" style="width:80px;" readonly>'
             . '<input type="text" name="subject_grade[]" class="subject-grade" placeholder="Grade" value="' . esc_attr( $grade ) . '" style="width:60px;" readonly>'
             . '<button type="button" class="button remove-detailed-subject">-</button>'
             . '</div></div>';
    }

    private function calculate_subject_grade( $percentage ) {
        if ( $percentage >= 90 ) return 'A+';
        if ( $percentage >= 80 ) return 'A';
        if ( $percentage >= 70 ) return 'B+';
        if ( $percentage >= 60 ) return 'B';
        if ( $percentage >= 50 ) return 'C';
        if ( $percentage >= 40 ) return 'D';
        return 'F';
    }

    public function save_result_data( $post_id, $post = null ) {
        if ( ! isset( $_POST['exam_result_nonce_field'] ) ||
             ! wp_verify_nonce( $_POST['exam_result_nonce_field'], 'exam_result_nonce' ) ) {
            return $post_id;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }
        if ( get_post_type( $post_id ) !== 'exam_result' ) {
            return $post_id;
        }

        $fields = array( 'student_name', 'student_class', 'student_section', 'student_rollno', 'student_semester', 'exam_year' );
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, "_$field", sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        $detailed_subjects = array();
        if ( isset( $_POST['subject_code'] ) && is_array( $_POST['subject_code'] ) ) {
            $codes = $_POST['subject_code'];
            $names = $_POST['subject_name'];
            $internals = $_POST['subject_internal'];
            $externals = $_POST['subject_external'];
            $practicals = $_POST['subject_practical'];
            for ( $i = 0; $i < count( $codes ); $i++ ) {
                if ( ! empty( $names[ $i ] ) ) {
                    $detailed_subjects[] = array(
                        'code'      => sanitize_text_field( $codes[ $i ] ),
                        'name'      => sanitize_text_field( $names[ $i ] ),
                        'internal'  => floatval( $internals[ $i ] ),
                        'external'  => floatval( $externals[ $i ] ),
                        'practical' => floatval( $practicals[ $i ] ),
                    );
                }
            }
        }
        update_post_meta( $post_id, '_detailed_subjects', $detailed_subjects );

        $max_internal = get_option( 'exam_max_internal', 10 );
        $max_external = get_option( 'exam_max_external', 70 );
        $max_practical = get_option( 'exam_max_practical', 20 );
        $max_per_subject = $max_internal + $max_external + $max_practical;

        $overall_total = 0;
        foreach ( $detailed_subjects as $subj ) {
            $overall_total += $subj['internal'] + $subj['external'] + $subj['practical'];
        }
        $max_overall = count( $detailed_subjects ) * $max_per_subject;
        $percentage = $max_overall > 0 ? ( $overall_total / $max_overall ) * 100 : 0;
        $overall_grade = $this->calculate_overall_grade( $percentage );

        update_post_meta( $post_id, '_student_marks', $overall_total );
        update_post_meta( $post_id, '_student_grade', $overall_grade );

        // Set post title to student name if empty
        if ( ! empty( $post ) && empty( $post->post_title ) && isset( $_POST['student_name'] ) ) {
            $title = sanitize_text_field( $_POST['student_name'] );
            if ( ! empty( $title ) ) {
                wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
            }
        }
    }

    private function calculate_overall_grade( $percentage ) {
        if ( $percentage >= 90 ) return 'A+';
        if ( $percentage >= 80 ) return 'A';
        if ( $percentage >= 70 ) return 'B+';
        if ( $percentage >= 60 ) return 'B';
        if ( $percentage >= 50 ) return 'C';
        if ( $percentage >= 40 ) return 'D';
        return 'F';
    }

    // Admin settings page
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=exam_result',
            __( 'Marksheet Settings', 'exam-result-manager' ),
            __( 'Marksheet Settings', 'exam-result-manager' ),
            'manage_options',
            'exam-result-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'exam_result_settings_group', 'exam_institute_name', 'sanitize_text_field' );
        register_setting( 'exam_result_settings_group', 'exam_institute_logo', 'esc_url_raw' );
        register_setting( 'exam_result_settings_group', 'exam_institute_tagline', 'sanitize_text_field' );
        register_setting( 'exam_result_settings_group', 'exam_logo_width', array( $this, 'sanitize_logo_width' ) );
        register_setting( 'exam_result_settings_group', 'exam_logo_title_gap', array( $this, 'sanitize_logo_gap' ) );
        register_setting( 'exam_result_settings_group', 'exam_header_layout', array( $this, 'sanitize_header_layout' ) );
        register_setting( 'exam_result_settings_group', 'exam_header_align', array( $this, 'sanitize_align' ) );
        register_setting( 'exam_result_settings_group', 'exam_title_size', array( $this, 'sanitize_title_size' ) );
        register_setting( 'exam_result_settings_group', 'exam_tagline_size', array( $this, 'sanitize_tagline_size' ) );
        register_setting( 'exam_result_settings_group', 'exam_max_internal', 'absint' );
        register_setting( 'exam_result_settings_group', 'exam_max_external', 'absint' );
        register_setting( 'exam_result_settings_group', 'exam_max_practical', 'absint' );
    }

    // Keep logo width within a sane, printable range (px)
    public function sanitize_logo_width( $value ) {
        $value = absint( $value );
        if ( $value < 20 ) {
            $value = 20;
        }
        if ( $value > 400 ) {
            $value = 400;
        }
        return $value;
    }

    // Keep the logo/title gap within a sane range (px)
    public function sanitize_logo_gap( $value ) {
        $value = absint( $value );
        if ( $value > 200 ) {
            $value = 200;
        }
        return $value;
    }

    // Logo position relative to the title/tagline block
    public function sanitize_header_layout( $value ) {
        $allowed = array( 'logo_left', 'logo_right', 'logo_top' );
        return in_array( $value, $allowed, true ) ? $value : 'logo_left';
    }

    // Shared left/center/right alignment (title+tagline block, and logo when stacked on top)
    public function sanitize_align( $value ) {
        $allowed = array( 'left', 'center', 'right' );
        return in_array( $value, $allowed, true ) ? $value : 'left';
    }

    // Title font size (px)
    public function sanitize_title_size( $value ) {
        $value = absint( $value );
        if ( $value < 10 ) {
            $value = 10;
        }
        if ( $value > 72 ) {
            $value = 72;
        }
        return $value;
    }

    // Tagline font size (px)
    public function sanitize_tagline_size( $value ) {
        $value = absint( $value );
        if ( $value < 8 ) {
            $value = 8;
        }
        if ( $value > 48 ) {
            $value = 48;
        }
        return $value;
    }

    public function render_settings_page() {
        $logo_url     = get_option( 'exam_institute_logo', '' );
        $logo_width   = get_option( 'exam_logo_width', 120 );
        $logo_gap     = get_option( 'exam_logo_title_gap', 20 );
        $institute    = get_option( 'exam_institute_name', '' );
        $tagline      = get_option( 'exam_institute_tagline', '' );
        $layout       = get_option( 'exam_header_layout', 'logo_left' );
        $align        = get_option( 'exam_header_align', 'left' );
        $title_size   = get_option( 'exam_title_size', 24 );
        $tagline_size = get_option( 'exam_tagline_size', 14 );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Marksheet Settings', 'exam-result-manager' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'exam_result_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="exam_institute_name"><?php _e( 'Institute Name', 'exam-result-manager' ); ?></label></th>
                        <td><input type="text" name="exam_institute_name" id="exam_institute_name" value="<?php echo esc_attr( $institute ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_institute_tagline"><?php _e( 'Tagline (optional)', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <input type="text" name="exam_institute_tagline" id="exam_institute_tagline" value="<?php echo esc_attr( $tagline ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Affiliated to State Board of Education', 'exam-result-manager' ); ?>" />
                            <p class="description"><?php _e( 'A short line shown below the institute name, wherever it appears (search results and printed marksheets). Leave blank to hide it.', 'exam-result-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_institute_logo"><?php _e( 'Institute Logo (URL)', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <input type="text" name="exam_institute_logo" id="exam_institute_logo" value="<?php echo esc_attr( $logo_url ); ?>" class="regular-text" />
                            <button type="button" class="button" id="upload_logo_button"><?php _e( 'Upload Logo', 'exam-result-manager' ); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_logo_width"><?php _e( 'Logo Width', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <input type="number" name="exam_logo_width" id="exam_logo_width" value="<?php echo esc_attr( $logo_width ); ?>" min="20" max="400" step="1" style="width:90px;"> px
                            <p class="description"><?php _e( 'Logo display width in pixels (20-400). Height scales automatically to keep the logo proportional.', 'exam-result-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_header_layout"><?php _e( 'Logo Position', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <select name="exam_header_layout" id="exam_header_layout">
                                <option value="logo_left" <?php selected( $layout, 'logo_left' ); ?>><?php _e( 'Left of title', 'exam-result-manager' ); ?></option>
                                <option value="logo_right" <?php selected( $layout, 'logo_right' ); ?>><?php _e( 'Right of title', 'exam-result-manager' ); ?></option>
                                <option value="logo_top" <?php selected( $layout, 'logo_top' ); ?>><?php _e( 'Above title', 'exam-result-manager' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Where the logo sits relative to the institute name and tagline.', 'exam-result-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_logo_title_gap"><?php _e( 'Logo &ndash; Title Gap', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <input type="number" name="exam_logo_title_gap" id="exam_logo_title_gap" value="<?php echo esc_attr( $logo_gap ); ?>" min="0" max="200" step="1" style="width:90px;"> px
                            <p class="description"><?php _e( 'Spacing between the logo and the institute name/tagline block (horizontal if the logo is left/right, vertical if it is above).', 'exam-result-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_header_align"><?php _e( 'Header Alignment', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <select name="exam_header_align" id="exam_header_align">
                                <option value="left" <?php selected( $align, 'left' ); ?>><?php _e( 'Left', 'exam-result-manager' ); ?></option>
                                <option value="center" <?php selected( $align, 'center' ); ?>><?php _e( 'Center', 'exam-result-manager' ); ?></option>
                                <option value="right" <?php selected( $align, 'right' ); ?>><?php _e( 'Right', 'exam-result-manager' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Text alignment of the title/tagline block. Also controls where the logo sits when "Above title" is selected.', 'exam-result-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_title_size"><?php _e( 'Title Font Size', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <input type="number" name="exam_title_size" id="exam_title_size" value="<?php echo esc_attr( $title_size ); ?>" min="10" max="72" step="1" style="width:90px;"> px
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_tagline_size"><?php _e( 'Tagline Font Size', 'exam-result-manager' ); ?></label></th>
                        <td>
                            <input type="number" name="exam_tagline_size" id="exam_tagline_size" value="<?php echo esc_attr( $tagline_size ); ?>" min="8" max="48" step="1" style="width:90px;"> px
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Header Preview', 'exam-result-manager' ); ?></th>
                        <td>
                            <?php $this->render_institute_header( 'preview' ); ?>
                            <p class="description"><?php _e( 'This preview updates live as you edit the fields above (save to apply on the site).', 'exam-result-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Maximum Marks per Subject Component', 'exam-result-manager' ); ?></th>
                        <td>
                            <label><?php _e( 'Internal:', 'exam-result-manager' ); ?></label>
                            <input type="number" name="exam_max_internal" value="<?php echo esc_attr( get_option( 'exam_max_internal', 10 ) ); ?>" step="1" style="width:80px;">
                            <label style="margin-left:15px;"><?php _e( 'External:', 'exam-result-manager' ); ?></label>
                            <input type="number" name="exam_max_external" value="<?php echo esc_attr( get_option( 'exam_max_external', 70 ) ); ?>" step="1" style="width:80px;">
                            <label style="margin-left:15px;"><?php _e( 'Practical:', 'exam-result-manager' ); ?></label>
                            <input type="number" name="exam_max_practical" value="<?php echo esc_attr( get_option( 'exam_max_practical', 20 ) ); ?>" step="1" style="width:80px;">
                            <p class="description"><?php _e( 'These values are used to calculate subject totals and overall percentage. They apply to all subjects.', 'exam-result-manager' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#upload_logo_button').click(function(e) {
                e.preventDefault();
                var custom_uploader = wp.media({
                    title: '<?php _e( 'Select Logo', 'exam-result-manager' ); ?>',
                    button: { text: '<?php _e( 'Use this image', 'exam-result-manager' ); ?>' },
                    multiple: false
                });
                custom_uploader.on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    $('#exam_institute_logo').val(attachment.url);
                    rebuildPreview();
                });
                custom_uploader.open();
            });

            // Live preview: any change to a relevant field re-renders the whole
            // preview block via AJAX-free client-side rebuild, so layout changes
            // (logo position, alignment) are reflected exactly, not just CSS tweaks.
            var fields = [
                '#exam_institute_name', '#exam_institute_tagline', '#exam_institute_logo',
                '#exam_logo_width', '#exam_logo_title_gap', '#exam_header_layout',
                '#exam_header_align', '#exam_title_size', '#exam_tagline_size'
            ];

            function currentValues() {
                return {
                    name: $('#exam_institute_name').val(),
                    tagline: $('#exam_institute_tagline').val(),
                    logo: $('#exam_institute_logo').val(),
                    logoWidth: parseInt($('#exam_logo_width').val(), 10) || 0,
                    gap: parseInt($('#exam_logo_title_gap').val(), 10) || 0,
                    layout: $('#exam_header_layout').val(),
                    align: $('#exam_header_align').val(),
                    titleSize: parseInt($('#exam_title_size').val(), 10) || 24,
                    taglineSize: parseInt($('#exam_tagline_size').val(), 10) || 14
                };
            }

            function rebuildPreview() {
                var v = currentValues();
                var $wrap = $('#erm-header-preview-wrap');
                $wrap.empty();

                var flexDirection = (v.layout === 'logo_top') ? 'column' : (v.layout === 'logo_right' ? 'row-reverse' : 'row');
                var alignItems = (v.layout === 'logo_top') ? (v.align === 'center' ? 'center' : (v.align === 'right' ? 'flex-end' : 'flex-start')) : 'center';
                var justifyContent = (v.align === 'center') ? 'center' : (v.align === 'right' ? 'flex-end' : 'flex-start');

                var containerCss = {
                    display: 'flex',
                    flexDirection: flexDirection,
                    alignItems: alignItems,
                    gap: v.gap + 'px',
                    border: '1px solid #ddd',
                    background: '#fff',
                    padding: '16px',
                    maxWidth: '600px',
                    width: '100%',
                    boxSizing: 'border-box',
                    flexWrap: 'wrap'
                };
                if (v.layout !== 'logo_top') {
                    containerCss.justifyContent = justifyContent;
                }
                var $container = $('<div></div>').css(containerCss);

                if (v.logo) {
                    $container.append(
                        $('<img>').attr('src', v.logo).css({ width: v.logoWidth + 'px', maxWidth: '100%', height: 'auto', flex: '0 0 auto', display: 'block' })
                    );
                }

                var $textBlock = $('<div></div>').css({ textAlign: v.align, width: (v.layout === 'logo_top') ? '100%' : 'auto' });
                if (v.name) {
                    $textBlock.append($('<div></div>').css({ fontWeight: 700, color: '#0f172a', fontSize: v.titleSize + 'px' }).text(v.name));
                } else {
                    $textBlock.append($('<div></div>').css({ fontWeight: 700, color: '#0f172a', fontSize: v.titleSize + 'px' }).text('<?php echo esc_js( __( 'Institute Name', 'exam-result-manager' ) ); ?>'));
                }
                if (v.tagline) {
                    $textBlock.append($('<div></div>').css({ color: '#64748b', fontStyle: 'italic', marginTop: '4px', fontSize: v.taglineSize + 'px' }).text(v.tagline));
                }
                $container.append($textBlock);
                $wrap.append($container);
            }

            fields.forEach(function(sel) {
                $(sel).on('input change', rebuildPreview);
            });

            // Initial paint of the preview block.
            rebuildPreview();
        });
        </script>
        <?php
    }

    /**
     * Renders the institute header (logo + name + tagline) using the saved
     * layout settings. Shared by the settings-page preview, the frontend
     * result card, and the printable marksheet so all three stay in sync.
     *
     * @param string $context 'frontend' | 'print' | 'preview'
     */
    private function render_institute_header( $context = 'frontend' ) {
        // The settings-page preview is fully owned and rebuilt by client-side
        // JS (see rebuildPreview() in render_settings_page()) so that changing
        // layout/alignment/size fields updates instantly without a page reload.
        // We just render the empty wrapper here; JS fills it on page load.
        if ( 'preview' === $context ) {
            echo '<div id="erm-header-preview-wrap"></div>';
            return;
        }

        $institute_name = get_option( 'exam_institute_name', '' );
        $logo_url       = get_option( 'exam_institute_logo', '' );
        $tagline        = get_option( 'exam_institute_tagline', '' );
        $logo_width     = get_option( 'exam_logo_width', 120 );
        $logo_gap       = get_option( 'exam_logo_title_gap', 20 );
        $layout         = get_option( 'exam_header_layout', 'logo_left' );
        $align          = get_option( 'exam_header_align', 'left' );
        $title_size     = get_option( 'exam_title_size', 24 );
        $tagline_size   = get_option( 'exam_tagline_size', 14 );

        if ( ! $institute_name && ! $logo_url ) {
            return;
        }

        $flex_direction = ( 'logo_top' === $layout ) ? 'column' : ( 'logo_right' === $layout ? 'row-reverse' : 'row' );
        $align_items    = ( 'logo_top' === $layout )
            ? ( 'center' === $align ? 'center' : ( 'right' === $align ? 'flex-end' : 'flex-start' ) )
            : 'center';
        // For row layouts (logo left/right of title), the chosen alignment
        // moves the whole logo+text group within the available width.
        $justify_content = ( 'center' === $align ) ? 'center' : ( 'right' === $align ? 'flex-end' : 'flex-start' );

        $wrapper_style = sprintf(
            'display:flex; flex-direction:%s; align-items:%s; gap:%dpx; flex-wrap:wrap; width:100%%;',
            esc_attr( $flex_direction ),
            esc_attr( $align_items ),
            intval( $logo_gap )
        );

        if ( 'logo_top' !== $layout ) {
            $wrapper_style .= ' justify-content:' . esc_attr( $justify_content ) . ';';
        }

        if ( 'print' === $context ) {
            $wrapper_style .= ' margin-bottom:30px; border-bottom:2px solid #2563eb; padding-bottom:20px;';
        } elseif ( 'frontend' === $context ) {
            $wrapper_style .= ' margin-bottom:18px;';
        }

        echo '<div class="exam-result-institute-header" style="' . esc_attr( $wrapper_style ) . '">';

        if ( $logo_url ) {
            printf(
                '<img src="%s" alt="%s" style="width:%dpx; max-width:100%%; height:auto; flex:0 0 auto; display:block;">',
                esc_url( $logo_url ),
                esc_attr( $institute_name ),
                intval( $logo_width )
            );
        }

        if ( $institute_name || $tagline ) {
            $text_style = sprintf( 'text-align:%s; %s', esc_attr( $align ), ( 'logo_top' === $layout ) ? 'width:100%;' : '' );
            echo '<div style="' . esc_attr( $text_style ) . '">';
            if ( $institute_name ) {
                printf(
                    '<div class="exam-result-institute-name" style="font-weight:700; color:#0f172a; font-size:%dpx; line-height:1.3;">%s</div>',
                    intval( $title_size ),
                    esc_html( $institute_name )
                );
            }
            if ( $tagline ) {
                printf(
                    '<div class="exam-result-institute-tagline" style="color:#64748b; font-style:italic; margin-top:4px; font-size:%dpx; line-height:1.3;">%s</div>',
                    intval( $tagline_size ),
                    esc_html( $tagline )
                );
            }
            echo '</div>';
        }

        echo '</div>';
    }

    // Admin manager page
    public function add_manager_page() {
        add_submenu_page(
            'edit.php?post_type=exam_result',
            __( 'Result Manager', 'exam-result-manager' ),
            __( 'Result Manager', 'exam-result-manager' ),
            'manage_options',
            'exam-result-manager',
            array( $this, 'render_manager_page' )
        );
    }

    public function render_manager_page() {
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $query = new WP_Query( array(
            'post_type'      => 'exam_result',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'post_status'    => 'publish',
        ) );

        echo '<div class="wrap"><h1>' . __( 'Exam Result Manager', 'exam-result-manager' ) . '</h1>';

        echo '<div class="card" style="max-width: 600px; margin-bottom: 20px;">';
        echo '<h2>' . __( 'Import Results from CSV', 'exam-result-manager' ) . '</h2>';
        echo '<form method="post" action="' . admin_url( 'admin-post.php' ) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="import_exam_results">';
        wp_nonce_field( 'import_exam_results_nonce' );
        echo '<p><input type="file" name="csv_file" accept=".csv" required></p>';
        echo '<p><label><input type="checkbox" name="skip_header" value="1"> ' . __( 'First row is header (skip it)', 'exam-result-manager' ) . '</label></p>';
        echo '<p><strong>' . __( 'CSV Format (detailed):', 'exam-result-manager' ) . '</strong> ' . __( 'Roll No, Name, Class, Section, Semester, Year, Code1, Subject1, Internal1, External1, Practical1, Code2, Subject2, Internal2, External2, Practical2, ...', 'exam-result-manager' ) . '</p>';
        echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Import Results', 'exam-result-manager' ) . '"></p>';
        echo '</form>';
        echo '</div>';

        if ( $query->have_posts() ) {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>
                <th>' . __( 'Name', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Class', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Section', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Roll No', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Semester', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Year', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Total Marks', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Grade', 'exam-result-manager' ) . '</th>
                <th>' . __( 'Actions', 'exam-result-manager' ) . '</th>
               </tr></thead><tbody>';

            while ( $query->have_posts() ) : $query->the_post();
                $id = get_the_ID();
                $name      = esc_html( get_post_meta( $id, '_student_name', true ) );
                $class     = esc_html( get_post_meta( $id, '_student_class', true ) );
                $section   = esc_html( get_post_meta( $id, '_student_section', true ) );
                $rollno    = esc_html( get_post_meta( $id, '_student_rollno', true ) );
                $semester  = esc_html( get_post_meta( $id, '_student_semester', true ) );
                $year      = esc_html( get_post_meta( $id, '_exam_year', true ) );
                $total     = esc_html( get_post_meta( $id, '_student_marks', true ) );
                $grade     = esc_html( get_post_meta( $id, '_student_grade', true ) );
                $edit_link   = get_edit_post_link( $id );
                $delete_link = get_delete_post_link( $id );
                echo "<tr>
                    <td>$name</td>
                    <td>$class</td>
                    <td>$section</td>
                    <td>$rollno</td>
                    <td>$semester</td>
                    <td>$year</td>
                    <td>$total</td>
                    <td>$grade</td>
                    <td>
                        <a href='$edit_link' class='button'>" . __( 'Edit', 'exam-result-manager' ) . "</a>
                        <a href='$delete_link' class='button button-danger' onclick=\"return confirm('" . esc_js( __( 'Are you sure?', 'exam-result-manager' ) ) . "')\">" . __( 'Delete', 'exam-result-manager' ) . "</a>
                    </td>
                </tr>";
            endwhile;
            echo '</tbody></table>';

            $total_pages = $query->max_num_pages;
            if ( $total_pages > 1 ) {
                $page_links = paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $paged,
                ) );
                if ( $page_links ) {
                    echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
                }
            }
        } else {
            echo '<p>' . __( 'No exam results found.', 'exam-result-manager' ) . ' <a href="' . admin_url( 'post-new.php?post_type=exam_result' ) . '">' . __( 'Add your first result', 'exam-result-manager' ) . '</a></p>';
        }

        wp_reset_postdata();
        echo '</div>';
    }

    // CSV import handler
    public function handle_csv_import() {
        if ( ! isset( $_POST['_wpnonce'] ) ||
             ! wp_verify_nonce( $_POST['_wpnonce'], 'import_exam_results_nonce' ) ||
             ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Security check failed.', 'exam-result-manager' ) );
        }

        if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
            wp_die( __( 'No file uploaded.', 'exam-result-manager' ) );
        }

        $file = $_FILES['csv_file'];
        $filetype = wp_check_filetype( $file['name'] );
        if ( $filetype['ext'] !== 'csv' ) {
            wp_die( __( 'Only CSV files are allowed.', 'exam-result-manager' ) );
        }

        $skip_header = isset( $_POST['skip_header'] ) ? true : false;
        $imported = 0;
        $errors = 0;

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( $skip_header ) {
            fgetcsv( $handle );
        }

        $max_internal = get_option( 'exam_max_internal', 10 );
        $max_external = get_option( 'exam_max_external', 70 );
        $max_practical = get_option( 'exam_max_practical', 20 );
        $max_per_subject = $max_internal + $max_external + $max_practical;

        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            if ( count( $data ) < 6 ) {
                $errors++;
                continue;
            }
            $rollno   = sanitize_text_field( $data[0] );
            $name     = sanitize_text_field( $data[1] );
            $class    = sanitize_text_field( $data[2] );
            $section  = sanitize_text_field( $data[3] );
            $semester = sanitize_text_field( $data[4] );
            $year     = sanitize_text_field( $data[5] );

            $existing = get_posts( array(
                'post_type'      => 'exam_result',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array( 'key' => '_student_rollno',   'value' => $rollno ),
                    array( 'key' => '_student_class',    'value' => $class ),
                    array( 'key' => '_student_section',  'value' => $section ),
                    array( 'key' => '_student_semester', 'value' => $semester ),
                    array( 'key' => '_exam_year',        'value' => $year ),
                ),
            ) );
            if ( ! empty( $existing ) ) {
                $errors++;
                continue;
            }

            $detailed_subjects = array();
            $overall_total = 0;
            for ( $i = 6; $i < count( $data ); $i += 5 ) {
                if ( $i + 4 >= count( $data ) ) break;
                $code = sanitize_text_field( $data[ $i ] );
                $subj_name = sanitize_text_field( $data[ $i + 1 ] );
                $internal = floatval( $data[ $i + 2 ] );
                $external = floatval( $data[ $i + 3 ] );
                $practical = floatval( $data[ $i + 4 ] );
                if ( empty( $subj_name ) ) continue;
                $detailed_subjects[] = array(
                    'code'      => $code,
                    'name'      => $subj_name,
                    'internal'  => $internal,
                    'external'  => $external,
                    'practical' => $practical,
                );
                $overall_total += $internal + $external + $practical;
            }
            if ( empty( $detailed_subjects ) ) {
                $errors++;
                continue;
            }

            $post_id = wp_insert_post( array(
                'post_type'   => 'exam_result',
                'post_title'  => $name,
                'post_status' => 'publish',
            ) );
            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_student_rollno',   $rollno );
                update_post_meta( $post_id, '_student_name',     $name );
                update_post_meta( $post_id, '_student_class',    $class );
                update_post_meta( $post_id, '_student_section',  $section );
                update_post_meta( $post_id, '_student_semester', $semester );
                update_post_meta( $post_id, '_exam_year',        $year );
                update_post_meta( $post_id, '_detailed_subjects', $detailed_subjects );
                $max_overall = count( $detailed_subjects ) * $max_per_subject;
                $percentage = $max_overall > 0 ? ( $overall_total / $max_overall ) * 100 : 0;
                $overall_grade = $this->calculate_overall_grade( $percentage );
                update_post_meta( $post_id, '_student_marks', $overall_total );
                update_post_meta( $post_id, '_student_grade', $overall_grade );
                $imported++;
            } else {
                $errors++;
            }
        }
        fclose( $handle );

        if ( $imported ) {
            add_action( 'admin_notices', function() use ( $imported ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Successfully imported %d results.', 'exam-result-manager' ), $imported ) . '</p></div>';
            } );
        }
        if ( $errors ) {
            add_action( 'admin_notices', function() use ( $errors ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . sprintf( __( 'Failed to import %d results (duplicates or invalid data).', 'exam-result-manager' ), $errors ) . '</p></div>';
            } );
        }

        wp_redirect( admin_url( 'edit.php?post_type=exam_result&page=exam-result-manager' ) );
        exit;
    }

    // Enqueue frontend CSS and JS
    public function enqueue_frontend_scripts() {
        if ( ! is_admin() ) {
            wp_enqueue_style( 'exam-result-style', ERM_PLUGIN_URL . 'assets/exam-result.css', array(), ERM_VERSION );
            wp_enqueue_script( 'exam-result-script', ERM_PLUGIN_URL . 'assets/exam-result.js', array( 'jquery' ), ERM_VERSION, true );
            wp_localize_script( 'exam-result-script', 'examResultAjax', array(
                'ajaxurl'    => admin_url( 'admin-ajax.php' ),
                'print_nonce'=> wp_create_nonce( 'print_marksheet_nonce' ),
            ) );
        }
    }

    // Result search shortcode
    public function render_result_search() {
        ob_start();
        ?>
        <div class="exam-result-search-container">
            <?php
            $search_roll   = isset( $_POST['search_rollno'] ) ? sanitize_text_field( $_POST['search_rollno'] ) : '';
            $search_class  = isset( $_POST['search_class'] ) ? sanitize_text_field( $_POST['search_class'] ) : '';
            $search_sem    = isset( $_POST['search_semester'] ) ? sanitize_text_field( $_POST['search_semester'] ) : '';
            $search_year   = isset( $_POST['search_year'] ) ? sanitize_text_field( $_POST['search_year'] ) : '';
            $search_section = isset( $_POST['search_section'] ) ? sanitize_text_field( $_POST['search_section'] ) : '';

            if ( $search_roll && $search_class && $search_sem && $search_year ) {
                $meta_query = array( 'relation' => 'AND' );
                $meta_query[] = array( 'key' => '_student_rollno',   'value' => $search_roll,   'compare' => '=' );
                $meta_query[] = array( 'key' => '_student_class',    'value' => $search_class,  'compare' => '=' );
                $meta_query[] = array( 'key' => '_student_semester', 'value' => $search_sem,    'compare' => '=' );
                $meta_query[] = array( 'key' => '_exam_year',        'value' => $search_year,   'compare' => '=' );
                if ( ! empty( $search_section ) ) {
                    $meta_query[] = array( 'key' => '_student_section', 'value' => $search_section, 'compare' => '=' );
                }

                $query = new WP_Query( array(
                    'post_type'      => 'exam_result',
                    'posts_per_page' => 10,
                    'meta_query'     => $meta_query,
                ) );

                if ( $query->have_posts() ) {
                    while ( $query->have_posts() ) : $query->the_post();
                        $id = get_the_ID();
                        $name      = get_post_meta( $id, '_student_name', true );
                        $class     = get_post_meta( $id, '_student_class', true );
                        $section   = get_post_meta( $id, '_student_section', true );
                        $rollno    = get_post_meta( $id, '_student_rollno', true );
                        $semester  = get_post_meta( $id, '_student_semester', true );
                        $year      = get_post_meta( $id, '_exam_year', true );
                        $total     = get_post_meta( $id, '_student_marks', true );
                        $grade     = get_post_meta( $id, '_student_grade', true );
                        $subjects  = get_post_meta( $id, '_detailed_subjects', true );
                        ?>
                        <div class="exam-result" id="exam-result-<?php echo intval( $id ); ?>">
                            <?php $this->render_institute_header( 'frontend' ); ?>
                            <h3><?php _e( 'Exam Result', 'exam-result-manager' ); ?></h3>
                            <p><strong><?php _e( 'Name:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $name ); ?></p>
                            <p><strong><?php _e( 'Class:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $class ); ?></p>
                            <p><strong><?php _e( 'Section:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $section ); ?></p>
                            <p><strong><?php _e( 'Roll No:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $rollno ); ?></p>
                            <p><strong><?php _e( 'Semester:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $semester ); ?></p>
                            <p><strong><?php _e( 'Exam Year:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $year ); ?></p>
                            <?php if ( is_array( $subjects ) && ! empty( $subjects ) ) : ?>
                                <p><strong><?php _e( 'Subject-wise Marks:', 'exam-result-manager' ); ?></strong></p>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th><?php _e( 'Code', 'exam-result-manager' ); ?></th>
                                                <th><?php _e( 'Subject', 'exam-result-manager' ); ?></th>
                                                <th><?php _e( 'Internal', 'exam-result-manager' ); ?></th>
                                                <th><?php _e( 'External', 'exam-result-manager' ); ?></th>
                                                <th><?php _e( 'Practical', 'exam-result-manager' ); ?></th>
                                                <th><?php _e( 'Total', 'exam-result-manager' ); ?></th>
                                                <th><?php _e( 'Grade', 'exam-result-manager' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ( $subjects as $subj ) : 
                                            $subj_total = $subj['internal'] + $subj['external'] + $subj['practical'];
                                            $max_internal = get_option( 'exam_max_internal', 10 );
                                            $max_external = get_option( 'exam_max_external', 70 );
                                            $max_practical = get_option( 'exam_max_practical', 20 );
                                            $max_total = $max_internal + $max_external + $max_practical;
                                            $percentage = $max_total > 0 ? ( $subj_total / $max_total ) * 100 : 0;
                                            $subj_grade = $this->calculate_subject_grade( $percentage );
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html( $subj['code'] ); ?></td>
                                                <td><?php echo esc_html( $subj['name'] ); ?></td>
                                                <td><?php echo esc_html( $subj['internal'] ); ?></td>
                                                <td><?php echo esc_html( $subj['external'] ); ?></td>
                                                <td><?php echo esc_html( $subj['practical'] ); ?></td>
                                                <td><?php echo esc_html( $subj_total ); ?></td>
                                                <td><?php echo esc_html( $subj_grade ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <p><strong><?php _e( 'Overall Total:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $total ); ?></p>
                            <p><strong><?php _e( 'Overall Grade:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $grade ); ?></p>

                            <button class="print-button" onclick="printMarksheet(<?php echo intval( $id ); ?>)"><?php _e( 'Print Marksheet', 'exam-result-manager' ); ?></button>
                        </div>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                } else {
                    echo '<div class="exam-result-not-found"><p>' . __( 'No records found matching your search criteria.', 'exam-result-manager' ) . '</p></div>';
                }
            } else {
                if ( isset( $_POST['search_rollno'] ) ) {
                    echo '<div class="exam-result-not-found"><p>' . __( 'Please fill in all required fields: Roll Number, Class, Semester, and Year.', 'exam-result-manager' ) . '</p></div>';
                }
            }
            ?>

            <form method="post" class="exam-result-search-form">
                <p>
                    <div class="form-group">
                        <label for="search_rollno" class="required-field"><?php _e( 'Roll Number', 'exam-result-manager' ); ?></label>
                        <input type="text" id="search_rollno" name="search_rollno" placeholder="<?php esc_attr_e( 'Roll No', 'exam-result-manager' ); ?>" required value="<?php echo esc_attr( $search_roll ); ?>">
                    </div>
                    <div class="form-group">
                        <label for="search_class" class="required-field"><?php _e( 'Class', 'exam-result-manager' ); ?></label>
                        <input type="text" id="search_class" name="search_class" placeholder="<?php esc_attr_e( 'e.g., 10', 'exam-result-manager' ); ?>" required value="<?php echo esc_attr( $search_class ); ?>">
                    </div>
                    <div class="form-group">
                        <label for="search_semester" class="required-field"><?php _e( 'Semester', 'exam-result-manager' ); ?></label>
                        <input type="text" id="search_semester" name="search_semester" placeholder="<?php esc_attr_e( 'e.g., 1st', 'exam-result-manager' ); ?>" required value="<?php echo esc_attr( $search_sem ); ?>">
                    </div>
                    <div class="form-group">
                        <label for="search_year" class="required-field"><?php _e( 'Exam Year', 'exam-result-manager' ); ?></label>
                        <input type="text" id="search_year" name="search_year" placeholder="<?php esc_attr_e( 'e.g., 2024-2025', 'exam-result-manager' ); ?>" required value="<?php echo esc_attr( $search_year ); ?>">
                    </div>
                    <div class="form-group">
                        <label for="search_section"><?php _e( 'Section (optional)', 'exam-result-manager' ); ?></label>
                        <input type="text" id="search_section" name="search_section" placeholder="<?php esc_attr_e( 'e.g., A', 'exam-result-manager' ); ?>" value="<?php echo esc_attr( $search_section ); ?>">
                    </div>
                    <div class="form-group">
                        <input type="submit" value="<?php esc_attr_e( 'Search Result', 'exam-result-manager' ); ?>">
                    </div>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // AJAX handler for printable marksheet
    public function ajax_print_marksheet() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'print_marksheet_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'exam-result-manager' ) );
        }

        $post_id = intval( $_POST['post_id'] );
        if ( ! $post_id || get_post_type( $post_id ) !== 'exam_result' ) {
            echo '<p>' . __( 'Invalid result.', 'exam-result-manager' ) . '</p>';
            wp_die();
        }
        if ( 'publish' !== get_post_status( $post_id ) ) {
            // Matches the search shortcode's implicit publish-only scope —
            // draft/unpublished results shouldn't be printable by guessing an
            // ID, even though this endpoint is intentionally public otherwise.
            echo '<p>' . __( 'Invalid result.', 'exam-result-manager' ) . '</p>';
            wp_die();
        }

        $name      = get_post_meta( $post_id, '_student_name', true );
        $class     = get_post_meta( $post_id, '_student_class', true );
        $section   = get_post_meta( $post_id, '_student_section', true );
        $rollno    = get_post_meta( $post_id, '_student_rollno', true );
        $semester  = get_post_meta( $post_id, '_student_semester', true );
        $year      = get_post_meta( $post_id, '_exam_year', true );
        $total     = get_post_meta( $post_id, '_student_marks', true );
        $grade     = get_post_meta( $post_id, '_student_grade', true );
        $subjects  = get_post_meta( $post_id, '_detailed_subjects', true );
        if ( ! is_array( $subjects ) ) {
            // Legacy "_subject_marks"-only results (or otherwise missing
            // _detailed_subjects) return '' here. On PHP 8+, count('') on a
            // non-array is a fatal TypeError, so this must degrade to an
            // empty result set instead of crashing the print request.
            $subjects = array();
        }
        $max_internal = get_option( 'exam_max_internal', 10 );
        $max_external = get_option( 'exam_max_external', 70 );
        $max_practical = get_option( 'exam_max_practical', 20 );
        $max_total = $max_internal + $max_external + $max_practical;
        $max_overall = count( $subjects ) * $max_total;
        $overall_percentage = $max_overall > 0 ? round( ( $total / $max_overall ) * 100, 2 ) : 0;

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php _e( 'Marksheet', 'exam-result-manager' ); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
                    background: white;
                    padding: 20px;
                    color: #1e293b;
                }
                .marksheet {
                    max-width: 1000px;
                    margin: 0 auto;
                    border: 1px solid #cbd5e1;
                    padding: 30px 25px;
                    background: #fff;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                }
                .subtitle {
                    font-size: 16px;
                    color: #475569;
                    margin-top: 6px;
                }
                .student-details {
                    background: #f8fafc;
                    padding: 15px 20px;
                    margin: 20px 0;
                    border-left: 5px solid #2563eb;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 20px;
                    justify-content: space-between;
                }
                .student-details p {
                    margin: 5px 0;
                    font-size: 15px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 25px 0;
                    font-size: 14px;
                }
                th {
                    background: #2563eb;
                    color: white;
                    padding: 12px 8px;
                    border: 1px solid #334155;
                    text-align: left;
                }
                td {
                    padding: 10px 8px;
                    border: 1px solid #cbd5e1;
                }
                tr:nth-child(even) {
                    background: #f8fafc;
                }
                .total-grade {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f1f5f9;
                    text-align: right;
                    border-top: 2px solid #2563eb;
                }
                .total-grade p {
                    margin: 5px 0;
                    font-size: 16px;
                }
                .footer {
                    margin-top: 30px;
                    text-align: right;
                    font-size: 14px;
                    color: #475569;
                    border-top: 1px dashed #cbd5e1;
                    padding-top: 20px;
                }
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    .marksheet {
                        box-shadow: none;
                        border: none;
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
        <div class="marksheet">
            <?php $this->render_institute_header( 'print' ); ?>
            <div class="subtitle" style="text-align:center; margin-bottom:20px;">
                <?php _e( 'Statement of Marks', 'exam-result-manager' ); ?><br>
                <?php echo esc_html( $semester ) . ' Semester, ' . esc_html( $year ); ?>
            </div>

            <div class="student-details">
                <p><strong><?php _e( 'Name:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $name ); ?></p>
                <p><strong><?php _e( 'Class:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $class ); ?> &nbsp;|&nbsp; <strong><?php _e( 'Section:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $section ); ?></p>
                <p><strong><?php _e( 'Roll No:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $rollno ); ?></p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th><?php _e( 'Code', 'exam-result-manager' ); ?></th>
                        <th><?php _e( 'Subject', 'exam-result-manager' ); ?></th>
                        <th><?php _e( 'Internal', 'exam-result-manager' ); ?></th>
                        <th><?php _e( 'External', 'exam-result-manager' ); ?></th>
                        <th><?php _e( 'Practical', 'exam-result-manager' ); ?></th>
                        <th><?php _e( 'Total', 'exam-result-manager' ); ?></th>
                        <th><?php _e( 'Grade', 'exam-result-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $subjects as $subj ) :
                        $subj_total = $subj['internal'] + $subj['external'] + $subj['practical'];
                        $percentage = $max_total > 0 ? ( $subj_total / $max_total ) * 100 : 0;
                        $subj_grade = $this->calculate_subject_grade( $percentage );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $subj['code'] ); ?></td>
                        <td><?php echo esc_html( $subj['name'] ); ?></td>
                        <td><?php echo esc_html( $subj['internal'] ); ?></td>
                        <td><?php echo esc_html( $subj['external'] ); ?></td>
                        <td><?php echo esc_html( $subj['practical'] ); ?></td>
                        <td><?php echo esc_html( $subj_total ); ?></td>
                        <td><?php echo esc_html( $subj_grade ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-grade">
                <p><strong><?php _e( 'Overall Total:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $total ); ?> / <?php echo esc_html( $max_overall ); ?></p>
                <p><strong><?php _e( 'Percentage:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $overall_percentage ); ?>%</p>
                <p><strong><?php _e( 'Overall Grade:', 'exam-result-manager' ); ?></strong> <?php echo esc_html( $grade ); ?></p>
            </div>

            <div class="footer">
                <p><?php _e( 'Registrar Signature', 'exam-result-manager' ); ?></p>
                <p><?php echo date_i18n( get_option( 'date_format' ) ); ?></p>
            </div>
        </div>
        </body>
        </html>
        <?php
        wp_die();
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_media();
    }
}

new ExamResultManager();

register_uninstall_hook( __FILE__, 'exam_result_manager_uninstall' );
function exam_result_manager_uninstall() {
    $posts = get_posts( array(
        'post_type'      => 'exam_result',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ) );
    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }
    delete_option( 'exam_institute_name' );
    delete_option( 'exam_institute_logo' );
    delete_option( 'exam_institute_tagline' );
    delete_option( 'exam_logo_width' );
    delete_option( 'exam_logo_title_gap' );
    delete_option( 'exam_header_layout' );
    delete_option( 'exam_header_align' );
    delete_option( 'exam_title_size' );
    delete_option( 'exam_tagline_size' );
    delete_option( 'exam_max_internal' );
    delete_option( 'exam_max_external' );
    delete_option( 'exam_max_practical' );
    delete_option( 'erm_github_update_error' );
}
