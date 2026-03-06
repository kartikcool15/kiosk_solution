<div class="wrap">
    <h1>📊 Notification Analytics</h1>
    
    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 13px; margin-bottom: 5px;">TOTAL SUBSCRIBERS</div>
            <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format($total_subscribers); ?></div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 13px; margin-bottom: 5px;">NOTIFICATIONS SENT</div>
            <div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo number_format($total_notifications); ?></div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-left: 4px solid #9b51e0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 13px; margin-bottom: 5px;">DELIVERED</div>
            <div style="font-size: 32px; font-weight: bold; color: #9b51e0;"><?php echo number_format($total_delivered); ?></div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-left: 4px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 13px; margin-bottom: 5px;">CLICKED</div>
            <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo number_format($total_clicks); ?></div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-left: 4px solid #dc2626; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 13px; margin-bottom: 5px;">FAILED</div>
            <div style="font-size: 32px; font-weight: bold; color: #dc2626;"><?php echo number_format($total_failed); ?></div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-left: 4px solid #0891b2; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="color: #666; font-size: 13px; margin-bottom: 5px;">CLICK RATE</div>
            <div style="font-size: 32px; font-weight: bold; color: #0891b2;"><?php echo $ctr; ?>%</div>
        </div>
    </div>
    
    <!-- Notification History Table -->
    <div style="background: #fff; padding: 20px; margin-top: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0;">Recent Notifications</h2>
        
        <?php if (!empty($recent_notifications)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 25%;">Title</th>
                    <th style="width: 15%;">Post</th>
                    <th style="width: 10%;">Recipients</th>
                    <th style="width: 10%;">Delivered</th>
                    <th style="width: 10%;">Failed</th>
                    <th style="width: 10%;">Clicks</th>
                    <th style="width: 10%;">CTR</th>
                    <th style="width: 15%;">Sent At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_notifications as $notification): 
                    $notification_ctr = $notification->successful_sends > 0 
                        ? round(($notification->clicks / $notification->successful_sends) * 100, 1)
                        : 0;
                    $post_title = $notification->post_id > 0 ? get_the_title($notification->post_id) : 'N/A';
                ?>
                <tr>
                    <td><strong>#<?php echo $notification->id; ?></strong></td>
                    <td>
                        <strong><?php echo esc_html($notification->notification_title); ?></strong>
                        <?php if ($notification->notification_body): ?>
                        <br><small style="color: #666;"><?php echo esc_html(wp_trim_words($notification->notification_body, 10)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($notification->post_id > 0): ?>
                            <a href="<?php echo get_edit_post_link($notification->post_id); ?>" target="_blank">
                                <?php echo esc_html($post_title); ?>
                            </a>
                        <?php else: ?>
                            <span style="color: #999;">Test</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($notification->total_recipients); ?></td>
                    <td>
                        <span style="color: #00a32a; font-weight: bold;">
                            <?php echo number_format($notification->successful_sends); ?>
                        </span>
                    </td>
                    <td>
                        <span style="color: #dc2626; font-weight: bold;">
                            <?php echo number_format($notification->failed_sends); ?>
                        </span>
                    </td>
                    <td>
                        <span style="color: #f59e0b; font-weight: bold;">
                            <?php echo number_format($notification->clicks); ?>
                        </span>
                    </td>
                    <td>
                        <span style="color: #0891b2; font-weight: bold;">
                            <?php echo $notification_ctr; ?>%
                        </span>
                    </td>
                    <td>
                        <?php echo date('M j, Y g:i A', strtotime($notification->created_at)); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; padding: 40px 0; color: #666;">
            No notifications sent yet. Publish a post to send your first notification!
        </p>
        <?php endif; ?>
    </div>
    
    <div style="background: #f0f6fc; padding: 15px; margin-top: 20px; border-left: 4px solid #2271b1;">
        <h3 style="margin-top: 0;">💡 Tips</h3>
        <ul style="margin: 0;">
            <li><strong>Click Rate (CTR)</strong> shows how engaging your notifications are</li>
            <li>Failed sends are automatically cleaned up when tokens expire</li>
            <li>Click tracking works when users click on notifications</li>
            <li>Test notifications (post_id = 0) are for testing purposes only</li>
        </ul>
    </div>
</div>
