<?php
/**
 * Template Name: Athlete Dashboard
 *
 * @package AthleteDashboard
 */

get_header();

if ( is_user_logged_in() ) :
    $current_user = wp_get_current_user();
    ?>
    <div class="welcome-banner" id="welcomeBanner">
        <div class="welcome-content">
            <span class="user-icon">&#128100;</span>
            <span class="welcome-message">
                <?php
                printf(
                    /* translators: %s: user display name */
                    esc_html__( 'Welcome back, %s', 'athlete-dashboard' ),
                    '<strong>' . esc_html( $current_user->display_name ) . '</strong>'
                );
                ?>
            </span>
        </div>
        <button class="welcome-toggle" aria-label="<?php esc_attr_e( 'Toggle welcome message', 'athlete-dashboard' ); ?>">
            <span class="toggle-icon">&#9650;</span>
        </button>
    </div>

    <div class="athlete-dashboard">
        <div class="dashboard-content">
            <?php
            // Fitness Plan (full-width)
            render_dashboard_section( 'fitness-plan', 'Fitness Plan', '[user_fitness_plan]', 'full-width' );

            // Overview and Your Workouts (side-by-side)
            ?>
            <div class="dashboard-row">
                <?php
                render_dashboard_section( 'overview', 'Overview', '[user_overview]', 'half-width' );
                render_dashboard_section( 'workouts', 'Your Workouts', '[user_workouts]', 'half-width' );
                ?>
            </div>

            <?php
            // Nutrition (full-width)
            render_dashboard_section( 'nutrition', 'Nutrition', '[user_nutrition]', 'full-width' );

            // Upcoming Workouts and Account Details (side-by-side)
            ?>
            <div class="dashboard-row">
                <?php
                render_dashboard_section( 'upcoming-workouts', 'Upcoming Workouts', '[user_upcoming_workouts]', 'half-width' );
                render_dashboard_section( 'account-details', 'Account Details', 'account_details_content', 'half-width' );
                ?>
            </div>

            <?php
            // Body Weight Progress (full-width)
            render_dashboard_section( 'progress', 'Body Weight Progress', 'body_weight_progress_content', 'full-width' );

            // Comprehensive Body Composition (full-width)
            render_dashboard_section( 'comprehensive-body-composition', 'Comprehensive Body Composition', 'comprehensive_body_composition_content', 'full-width' );

            // Exercise Progress (full-width)
            render_dashboard_section( 'exercise-progress', 'Exercise Progress', 'exercise_progress_content', 'full-width' );
            ?>
        </div>
    </div>
    <?php
else :
    ?>
    <p>
        <?php
        printf(
            /* translators: %s: login URL */
            wp_kses(
                __( 'Please <a href="%s">log in</a> to view your dashboard.', 'athlete-dashboard' ),
                array(
                    'a' => array(
                        'href' => array(),
                    ),
                )
            ),
            esc_url( wp_login_url( get_permalink() ) )
        );
        ?>
    </p>
    <?php
endif;

get_footer();

/**
 * Render a dashboard section
 *
 * @param string $id Section ID.
 * @param string $title Section title.
 * @param string $content_callback Content callback function or shortcode.
 * @param string $width Section width class.
 */
function render_dashboard_section( $id, $title, $content_callback, $width ) {
    ?>
    <div id="<?php echo esc_attr( $id ); ?>" class="dashboard-section <?php echo esc_attr( $width ); ?>">
        <h2>
            <?php echo esc_html( $title ); ?>
            <button class="toggle-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-content">+</button>
        </h2>
        <div id="<?php echo esc_attr( $id ); ?>-content" class="section-content" aria-hidden="true">
            <?php
            if ( strpos( $content_callback, '[' ) === 0 ) {
                echo do_shortcode( $content_callback );
            } else {
                call_user_func( $content_callback );
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Generate account details content
 */
function account_details_content() {
    $current_user = wp_get_current_user();
    ?>
    <form id="account-details-form" class="custom-form">
        <div class="user-profile">
            <div class="profile-picture">
                <?php echo get_avatar( $current_user->ID, 150 ); ?>
                <input type="file" id="profile-picture-upload" name="profile_picture" accept="image/*" style="display: none;">
                <button id="change-avatar" class="custom-button"><?php esc_html_e( 'Change Picture', 'athlete-dashboard' ); ?></button>
            </div>
            <div class="profile-info">
                <p>
                    <strong><?php esc_html_e( 'Display Name:', 'athlete-dashboard' ); ?></strong>
                    <span id="display-name-text"><?php echo esc_html( $current_user->display_name ); ?></span>
                </p>
                <p>
                    <strong><?php esc_html_e( 'Email:', 'athlete-dashboard' ); ?></strong>
                    <span id="email-text"><?php echo esc_html( $current_user->user_email ); ?></span>
                </p>
                <p>
                    <strong><?php esc_html_e( 'Bio:', 'athlete-dashboard' ); ?></strong>
                    <span id="bio-text"><?php echo wp_kses_post( $current_user->description ); ?></span>
                </p>
            </div>
            <div class="edit-profile-fields" style="display: none;">
                <input type="text" name="display_name" value="<?php echo esc_attr( $current_user->display_name ); ?>" placeholder="<?php esc_attr_e( 'Display Name', 'athlete-dashboard' ); ?>">
                <input type="email" name="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" placeholder="<?php esc_attr_e( 'Email', 'athlete-dashboard' ); ?>">
                <textarea name="bio" placeholder="<?php esc_attr_e( 'Bio', 'athlete-dashboard' ); ?>"><?php echo esc_textarea( $current_user->description ); ?></textarea>
            </div>
        </div>
        <div class="profile-actions">
            <button id="edit-profile" class="custom-button"><?php esc_html_e( 'Edit Profile', 'athlete-dashboard' ); ?></button>
            <button id="save-profile" class="custom-button" style="display: none;"><?php esc_html_e( 'Save Profile', 'athlete-dashboard' ); ?></button>
            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="custom-button logout-button"><?php esc_html_e( 'Logout', 'athlete-dashboard' ); ?></a>
        </div>
    </form>
    <?php
}

/**
 * Generate body weight progress content
 */
function body_weight_progress_content() {
    echo do_shortcode( '[user_progress]' );
    ?>
    <div class="progress-chart-container full-width">
        <canvas id="progressChart"></canvas>
    </div>
    <form id="progress-form" class="progress-input-form custom-form">
        <div class="form-group">
            <label for="weight"><?php esc_html_e( 'Weight:', 'athlete-dashboard' ); ?></label>
            <div class="weight-input-group">
                <input type="number" id="weight" name="weight" required step="0.1">
                <select id="weight_unit" name="weight_unit">
                    <option value="kg"><?php esc_html_e( 'kg', 'athlete-dashboard' ); ?></option>
                    <option value="lbs"><?php esc_html_e( 'lbs', 'athlete-dashboard' ); ?></option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="date"><?php esc_html_e( 'Date:', 'athlete-dashboard' ); ?></label>
            <input type="date" id="date" name="date" required>
        </div>
        <button type="submit" class="custom-button"><?php esc_html_e( 'Add Progress', 'athlete-dashboard' ); ?></button>
    </form>
    <?php wp_nonce_field( 'athlete_dashboard_nonce', 'progress_nonce' ); ?>
    <?php
}

/**
 * Generate comprehensive body composition content
 */
function comprehensive_body_composition_content() {
    ?>
    <div class="chart-container">
        <canvas id="comprehensiveBodyCompositionChart"></canvas>
    </div>
    <div class="data-input-container">
        <form id="comprehensive-date-range-filter" class="date-range-form">
            <h3><?php esc_html_e('Filter Data', 'athlete-dashboard'); ?></h3>
            <div class="form-group">
                <label for="comprehensive-start-date"><?php esc_html_e('Start Date:', 'athlete-dashboard'); ?></label>
                <input type="date" id="comprehensive-start-date" name="start_date">
            </div>
            <div class="form-group">
                <label for="comprehensive-end-date"><?php esc_html_e('End Date:', 'athlete-dashboard'); ?></label>
                <input type="date" id="comprehensive-end-date" name="end_date">
            </div>
            <button type="submit" class="custom-button"><?php esc_html_e('Filter', 'athlete-dashboard'); ?></button>
        </form>
        <form id="comprehensive-body-composition-form" class="body-composition-form">
            <h3><?php esc_html_e('Add New Data', 'athlete-dashboard'); ?></h3>
            <div class="form-group">
                <label for="comprehensive-weight"><?php esc_html_e('Weight (kg):', 'athlete-dashboard'); ?></label>
                <input type="number" id="comprehensive-weight" name="weight" step="0.1" required>
            </div>
            <div class="form-group">
                <label for="comprehensive-body-fat"><?php esc_html_e('Body Fat (%):', 'athlete-dashboard'); ?></label>
                <input type="number" id="comprehensive-body-fat" name="body_fat_percentage" step="0.1" min="0" max="100">
            </div>
            <div class="form-group">
                <label for="comprehensive-muscle-mass"><?php esc_html_e('Muscle Mass (kg):', 'athlete-dashboard'); ?></label>
                <input type="number" id="comprehensive-muscle-mass" name="muscle_mass" step="0.1">
            </div>
            <div class="form-group">
                <label for="comprehensive-bmi"><?php esc_html_e('BMI:', 'athlete-dashboard'); ?></label>
                <input type="number" id="comprehensive-bmi" name="bmi" step="0.1">
            </div>
            <div class="form-group">
                <label for="comprehensive-measurement-date"><?php esc_html_e('Date:', 'athlete-dashboard'); ?></label>
                <input type="date" id="comprehensive-measurement-date" name="date" required>
            </div>
            <button type="submit" class="custom-button"><?php esc_html_e('Add Progress', 'athlete-dashboard'); ?></button>
        </form>
    </div>
    <?php wp_nonce_field( 'athlete_dashboard_nonce', 'comprehensive_progress_nonce' ); ?>
    <?php
}

/**
 * Generate exercise progress content
 */
function exercise_progress_content() {
    ?>
    <div id="exercise-tabs">
        <ul>
            <?php foreach ( get_exercise_tests() as $key => $test ) : ?>
                <li><a href="#<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $test['label'] ); ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php foreach ( get_exercise_tests() as $key => $test ) : ?>
            <div id="<?php echo esc_attr( $key ); ?>">
                <div class="progress-chart-container">
                    <canvas id="<?php echo esc_attr( $key ); ?>-chart"></canvas>
                </div>
                <form class="exercise-progress-form custom-form">
                    <input type="hidden" name="exercise_key" value="<?php echo esc_attr( $key ); ?>">
                    <div class="form-group">
                        <label for="<?php echo esc_attr( $key ); ?>-value"><?php echo esc_html( $test['label'] ); ?> (<?php echo esc_html( $test['unit'] ); ?>):</label>
                        <input type="number" id="<?php echo esc_attr( $key ); ?>-value" name="value" required step="<?php echo esc_attr( 1 / pow( 10, $test['decimal_places'] ) ); ?>">
                    </div>
                    <div class="form-group">
                        <label for="<?php echo esc_attr( $key ); ?>-date"><?php esc_html_e( 'Date:', 'athlete-dashboard' ); ?></label>
                        <input type="date" id="<?php echo esc_attr( $key ); ?>-date" name="date" required>
                    </div>
                    <button type="submit" class="custom-button"><?php esc_html_e( 'Add Progress', 'athlete-dashboard' ); ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php wp_nonce_field( 'athlete_dashboard_nonce', 'exercise_progress_nonce' ); ?>
    <?php
}
