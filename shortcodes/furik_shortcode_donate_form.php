<?php
/**
 * WordPress shortcode: [furik_donate_form], paramters: amount.
 */
function furik_shortcode_donate_form( $atts ) {
	global $furik_card_registration_statement_url, $furik_data_transmission_declaration_url, $furik_monthly_explanation_url, $furik_name_order_eastern;
    $a = shortcode_atts( array(
	   'amount' => '5000',
       'skip_message' => false,
       'enable_cash' => false,
       'enable_monthly' => false,
       'enable_newsletter' => false
    ), $atts );

    $amount = is_numeric($_GET['furik_amount']) ? $_GET['furik_amount'] : $atts['amount'];

    if (is_numeric($_GET['furik_campaign'])) {
        $post = get_post($_GET['furik_campaign']);
    }
    else {
        $post = get_post();
    }

    $amount_content = "";
    if ($post->post_type == 'campaign') {
        $campaign = $post->post_title;
        $campaign_id = $post->ID;
        $meta = get_post_custom($post->ID);

        if (isset($meta['AMOUNT_CONTENT'][0]) && $meta['AMOUNT_CONTENT'][0]) {
            $amount_content = $meta['AMOUNT_CONTENT'][0];
        }
        else {
            if ($post->post_parent) {
                $parent_campaign_meta = get_post_custom($post->post_parent);
                if (isset($parent_campaign_meta['AMOUNT_CONTENT'][0]) && $parent_campaign_meta['AMOUNT_CONTENT'][0]) {
                    $amount_content = $parent_campaign_meta['AMOUNT_CONTENT'][0];
                }
            }
        }
    }
    else {
        $campaign = __('General donation', 'furik');
    }

    return furik_load_template('furik_donate_form.php', get_defined_vars());
}

add_shortcode( 'furik_donate_form', 'furik_shortcode_donate_form' );