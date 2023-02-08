<?php
/*
Plugin Name: User Reviews
Plugin URI: https://example.com/user-reviews
Description: Allows users to submit reviews on the front-end of a website and display them on the website after they have been approved by an administrator
Version: 1.0
Author: John Doe
Author URI: https://example.com
*/

// Register custom post type for reviews
function create_review_post_type() {
    register_post_type( 'review',
        array(
            'labels' => array(
                'name' => __( 'Reviews' ),
                'singular_name' => __( 'Review' )
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array( 'title', 'editor', 'custom-fields' ),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'do_not_allow', // false < WP 4.5, credit @Ewout
            ),
            'map_meta_cap' => true, // Set to `false`, if users are not allowed to edit/delete existing posts
        )
    );
}
add_action( 'init', 'create_review_post_type' );

// Add custom meta fields for reviews
function add_review_meta_fields() {
    add_meta_box( 'review_meta_box',
        'Review Details',
        'display_review_meta_box',
        'review', 'normal', 'high'
    );
}
add_action( 'add_meta_boxes', 'add_review_meta_fields' );

// Display the custom meta fields for reviews
function display_review_meta_box( $review ) {
    $review_author = esc_html( get_post_meta( $review->ID, 'review_author', true ) );
    $review_author_email = esc_html( get_post_meta( $review->ID, 'review_author_email', true ) );
    $review_status = esc_html( get_post_meta( $review->ID, 'review_status', true ) );

    ?>
    <table>
        <tr>
            <td style="width: 100%">Author Name</td>
            <td><input type="text" size="80" name="review_author" value="<?php echo $review_author; ?>" /></td>
        </tr>
        <tr>
            <td style="width: 100%">Author Email</td>
            <td><input type="text" size="80" name="review_author_email" value="<?php echo $review_author_email; ?>" /></td>
        </tr>
        <tr>
            <td style="width: 100%">Status</td>
            <td>
                <select name="review_status">
                    <option value="pending" <?php selected( $review_status, 'pending' ); ?>>Pending</option>
                    <option value="approved" <?php selected( $review_status, 'approved' ); ?>>Approved</option>
                </select>
            </td>
        </tr>
    </table>
    <?php
}

// Save the custom meta fields for reviews
function save_review_meta_fields( $review_id ) {
    // Check if the current user has permission to save meta data
    if ( !current_user_can( 'edit_post', $review_id ) ) {
        return;
    }

    // Save the meta field values
    if ( isset( $_POST['review_author'] ) ) {
        update_post_meta( $review_id, 'review_author', sanitize_text_field( $_POST['review_author'] ) );
    }
    if ( isset( $_POST['review_author_email'] ) ) {
        update_post_meta( $review_id, 'review_author_email', sanitize_text_field( $_POST['review_author_email'] ) );
    }
    if ( isset( $_POST['review_status'] ) ) {
        update_post_meta( $review_id, 'review_status', sanitize_text_field( $_POST['review_status'] ) );
    }
}
add_action( 'save_post', 'save_review_meta_fields' );

// Add shortcode for displaying reviews
function reviews_shortcode() {
    // Get all approved reviews
    $reviews = get_posts( array(
        'post_type' => 'review',
        'meta_key' => 'review_status',
        'meta_value' => 'approved',
        'posts_per_page' => -1,
    ) );

    if ( $reviews ) {
        $output = '<ul>';
        foreach ( $reviews as $review ) {
            $output .= '<li>';
            $output .= '<h3>' . get_the_title( $review->ID ) . '</h3>';
            $output .= '<div>' . apply_filters( 'the_content', $review->post_content ) . '</div>';
            $output .= '<p>By: ' . get_post_meta( $review->ID, 'review_author', true ) . '</p>';
            $output .= '</li>';
        }
        $output .= '</ul>';
    } else {
        $output = '<p>No reviews found.</p>';
    }

    return $output;

}

add_shortcode( 'reviews', 'reviews_shortcode' );

// Add front-end form for submitting reviews
function reviews_form() {
    // Check if the user is logged in
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $author_name = $current_user->user_login;
        $author_email = $current_user->user_email;
    } else {
        $author_name = '';
        $author_email = '';
    }

    $output = '<form id="review_form" method="post" action="">
    <label for="review_rating">Rating</label>
    <div class="review_rating">
        <input type="radio" id="review_rating_5" name="review_rating" value="5">
        <label for="review_rating_5">5</label>
        <input type="radio" id="review_rating_4" name="review_rating" value="4">
        <label for="review_rating_4">4</label>
        <input type="radio" id="review_rating_3" name="review_rating" value="3">
        <label for="review_rating_3">3</label>
        <input type="radio" id="review_rating_2" name="review_rating" value="2">
        <label for="review_rating_2">2</label>
        <input type="radio" id="review_rating_1" name="review_rating" value="1">
        <label for="review_rating_1">1</label>
    </div>
    
        <br>
        <label for="review_content">Content</label>
        <textarea name="review_content" id="review_content"></textarea>
        <br>
        <label for="review_author_name">Author Name</label>
        <input type="text" name="review_author_name" id="review_author_name" value="' . $author_name . '">
        <br>
        <label for="review_author_email">Author Email</label>
        <input type="text" name="review_author_email" id="review_author_email" value="' . $author_email . '">
        <br>
        <input type="hidden" name="review_nonce" value="' . wp_create_nonce( 'review_nonce' ) . '">
        <input type="submit" name="review_submit" value="Submit">
    </form>';

    if ( isset( $_POST['review_submit'] ) ) {
        // Verify the nonce
        if ( !wp_verify_nonce( $_POST['review_nonce'], 'review_nonce' ) ) {
            $output .= '<p>Invalid nonce. Please try again.</p>';
        } else {
            // Create the review post
            $review_rating = sanitize_text_field( $_POST['review_rating'] );
            $review_content = sanitize_textarea_field( $_POST['review_content'] );
            $review_author_name = sanitize_text_field( $_POST['review_author_name'] );
            $review_author_email = sanitize_email( $_POST['review_author_email'] );

            $review_post = array(
                'post_title' => $review_rating,
                'post_content' => $review_content,
                'post_type' => 'review',
                'post_status' => 'pending',
            );

            $review_id = wp_insert_post( $review_post );

            if ( $review_id ) {
                // Save the custom meta fields
                update_post_meta( $review_id, 'review_author', $review_author_name );
                update_post_meta( $review_id, 'review_author_email', $review_author_email );
                update_post_meta( $review_id, 'review_status', 'pending' );

                $output .= '<p>Thank you for your review. It will be reviewed by an administrator and published soon.</p>';
                
                       // Redirect the user to the same page and clear the form data
                       $output .= '<script>setTimeout(function(){ window.location.href = "'.wp_get_referer().'"; }, 5000);</script>';

            } else {
                $output .= '<p>Error creating review. Please try again.</p>';
            }        
            if ( isset( $_POST['review_rating'] ) ) {
            $review_title = sanitize_text_field( $_POST['review_rating'] ) . ' stars';
            }
            if ( !isset($_POST['review_rating']) || empty($_POST['review_rating']) ) {
                $output .= '<p>Please select a rating</p>';
                return $output;
            }
        }
      
    }

    return $output;

}


add_shortcode( 'review_form', 'reviews_form' );

function review_install() {
    // Perform any installation tasks here
}

function review_uninstall() {
    // Perform any uninstallation tasks here
}
register_activation_hook(__FILE__, 'review_install');
register_deactivation_hook(__FILE__, 'review_uninstall');

