<?php
/**
 * Add dashboard widget to show upcoming recurring payments
 */
function furik_add_dashboard_widgets() {
    // Only add the widget for users with appropriate permissions
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'furik_upcoming_payments_widget',
            __('Upcoming Recurring Payments', 'furik'),
            'furik_upcoming_payments_widget_content'
        );
    }
}
add_action('wp_dashboard_setup', 'furik_add_dashboard_widgets');

/**
 * Content for the dashboard widget
 */
function furik_upcoming_payments_widget_content() {
    $upcoming_payments = furik_get_upcoming_recurring_payments(5); // Next 5 days
    
    if (empty($upcoming_payments)) {
        echo '<p>' . __('No upcoming recurring payments in the next 5 days.', 'furik') . '</p>';
        return;
    }
    
    $total_amount = array_sum(array_column($upcoming_payments, 'amount'));
    
    echo '<p>' . sprintf(
            __('Found %d upcoming payments totaling %s HUF in the next 5 days.', 'furik'),
            count($upcoming_payments),
            number_format($total_amount, 0, ',', ' ')
        ) . '</p>';
    
    echo '<table class="widefat" style="margin-bottom: 10px;">';
    echo '<thead>
            <tr>
                <th>' . __('Date', 'furik') . '</th>
                <th>' . __('Amount', 'furik') . '</th>
                <th>' . __('Donor', 'furik') . '</th>
                <th>' . __('Campaign', 'furik') . '</th>
            </tr>
          </thead>';
    echo '<tbody>';
    
    // Show only the first 5 items to keep the widget compact
    $display_count = min(count($upcoming_payments), 5);
    
    for ($i = 0; $i < $display_count; $i++) {
        $payment = $upcoming_payments[$i];
        $payment_date = new DateTime($payment->time);
        
        echo '<tr>';
        echo '<td>' . $payment_date->format('Y-m-d') . '</td>';
        echo '<td>' . number_format($payment->amount, 0, ',', ' ') . ' HUF</td>';
        echo '<td>' . esc_html($payment->donor_name) . '</td>';
        echo '<td>' . (empty($payment->campaign_name) ? __('General donation', 'furik') : esc_html($payment->campaign_name)) . '</td>';
        echo '</tr>';
    }
    
    // If there are more payments than we're showing, add a note
    if (count($upcoming_payments) > $display_count) {
        echo '<tr>
                <td colspan="4" style="text-align: center;">
                    <em>' . sprintf(__('... and %d more payments', 'furik'), count($upcoming_payments) - $display_count) . '</em>
                </td>
              </tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<p><a href="' . admin_url('admin.php?page=furik-batch-tools&tab=upcoming_payments') . '" class="button button-primary">' . 
        __('View All Upcoming Payments', 'furik') . 
    '</a></p>';
}
