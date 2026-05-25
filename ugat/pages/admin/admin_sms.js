// ============================================================
//  Admin SMS Notification Manager
//  pages/admin/admin_sms.js
//  
//  Frontend component for sending SMS notifications
// ============================================================

class AdminSmsNotificationManager {
    constructor() {
        this.baseUrl = '/pages/admin';
        this.notifications = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadNotificationHistory();
    }

    /**
     * Bind UI events
     */
    bindEvents() {
        // You can bind modal or form events here
        const sendBtn = document.getElementById('sms-send-btn');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.showSendModal());
        }
    }

    /**
     * Show SMS send modal
     */
    showSendModal() {
        const modal = document.createElement('div');
        modal.className = 'sms-modal';
        modal.innerHTML = `
            <div class="sms-modal-content">
                <span class="close-btn" onclick="this.parentElement.parentElement.remove()">&times;</span>
                <h2>Send SMS Notification</h2>
                <form id="sms-form">
                    <div class="form-group">
                        <label for="recipient-type">Send To:</label>
                        <select id="recipient-type" name="recipient_type" required onchange="handleRecipientTypeChange()">
                            <option value="">-- Select --</option>
                            <option value="single">Single Trainee</option>
                            <option value="group">Workshop Group</option>
                            <option value="all">All Trainees</option>
                        </select>
                    </div>

                    <div id="recipient-id-group" style="display:none;">
                        <label for="recipient-id">Trainee/Workshop ID:</label>
                        <input type="number" id="recipient-id" name="recipient_id">
                    </div>

                    <div class="form-group">
                        <label for="message-type">Message Type:</label>
                        <select id="message-type" name="message_type" onchange="handleMessageTypeChange()">
                            <option value="custom">Custom Message</option>
                            <option value="template">Use Template</option>
                        </select>
                    </div>

                    <div id="template-group" style="display:none;">
                        <label for="template">Select Template:</label>
                        <select id="template" name="template">
                            <option value="">-- Select Template --</option>
                            <option value="order_placed">Order Placed</option>
                            <option value="order_shipped">Order Shipped</option>
                            <option value="order_delivered">Order Delivered</option>
                            <option value="workshop_enrollment">Workshop Enrollment</option>
                            <option value="workshop_reminder">Workshop Reminder</option>
                            <option value="certification_issued">Certification Issued</option>
                            <option value="payment_received">Payment Received</option>
                        </select>
                    </div>

                    <div id="message-group">
                        <label for="message">Message:</label>
                        <textarea id="message" name="custom_message" rows="5" placeholder="Enter your message (max 160 characters)"></textarea>
                        <small id="char-count">0/160</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Send SMS</button>
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.sms-modal').remove()">Cancel</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);

        // Add styles
        this.addModalStyles();

        // Bind form submit
        document.getElementById('sms-form').addEventListener('submit', (e) => this.handleSmsSubmit(e));

        // Message character counter
        const msgInput = document.getElementById('message');
        msgInput.addEventListener('input', () => {
            document.getElementById('char-count').textContent = msgInput.value.length + '/160';
        });
    }

    /**
     * Handle SMS form submission
     */
    async handleSmsSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const recipientType = document.getElementById('recipient-type').value;
        const customMessage = document.getElementById('message').value.trim();

        if (!recipientType) {
            alert('Please select recipient type');
            return;
        }

        if (!customMessage) {
            alert('Please enter a message');
            return;
        }

        const payload = {
            recipient_type: recipientType,
            recipient_id: recipientType !== 'all' ? parseInt(document.getElementById('recipient-id').value) : 0,
            custom_message: customMessage,
            template: document.getElementById('template').value || ''
        };

        try {
            const response = await fetch(`${this.baseUrl}/send_sms_notification.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                alert(`✓ SMS sent successfully!\n\nSuccessful: ${result.details.successful}\nFailed: ${result.details.failed}`);
                form.closest('.sms-modal').remove();
                this.loadNotificationHistory(); // Refresh history
            } else {
                alert(`✗ Error: ${result.message}`);
            }
        } catch (error) {
            console.error('SMS send error:', error);
            alert('Failed to send SMS. Check console for details.');
        }
    }

    /**
     * Load notification history
     */
    async loadNotificationHistory() {
        try {
            const response = await fetch(`${this.baseUrl}/get_sms_notifications.php?limit=20`);
            const result = await response.json();

            if (result.success) {
                this.notifications = result.notifications;
                this.displayNotificationHistory();
            }
        } catch (error) {
            console.error('Failed to load notification history:', error);
        }
    }

    /**
     * Display notification history
     */
    displayNotificationHistory() {
        const historyContainer = document.getElementById('sms-history');
        if (!historyContainer) return;

        let html = '<h3>Recent SMS Notifications</h3>';
        
        if (this.notifications.length === 0) {
            html += '<p>No notifications sent yet.</p>';
        } else {
            html += '<table class="sms-history-table">';
            html += '<tr><th>Date</th><th>Recipients</th><th>Type</th><th>Count</th><th>Status</th></tr>';
            
            this.notifications.forEach(notif => {
                const date = new Date(notif.sent_at).toLocaleString();
                html += `<tr>
                    <td>${date}</td>
                    <td>${notif.recipient_type}</td>
                    <td>${notif.template || 'Custom'}</td>
                    <td>${notif.count}</td>
                    <td><span class="badge badge-${notif.status}">${notif.status}</span></td>
                </tr>`;
            });
            
            html += '</table>';
        }

        historyContainer.innerHTML = html;
    }

    /**
     * Add modal styles
     */
    addModalStyles() {
        if (document.getElementById('sms-modal-styles')) return;

        const style = document.createElement('style');
        style.id = 'sms-modal-styles';
        style.textContent = `
            .sms-modal {
                position: fixed;
                z-index: 9999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sms-modal-content {
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                max-width: 600px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            }

            .sms-modal-content h2 {
                margin-top: 0;
                color: #333;
                border-bottom: 2px solid #4CAF50;
                padding-bottom: 10px;
            }

            .close-btn {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .close-btn:hover {
                color: #000;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
                color: #333;
            }

            .form-group input[type="text"],
            .form-group input[type="number"],
            .form-group select,
            .form-group textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: Arial, sans-serif;
                font-size: 14px;
            }

            .form-group textarea {
                resize: vertical;
            }

            #char-count {
                display: block;
                margin-top: 5px;
                font-size: 12px;
                color: #999;
            }

            .form-actions {
                display: flex;
                gap: 10px;
                margin-top: 20px;
            }

            .btn {
                flex: 1;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
            }

            .btn-primary {
                background-color: #4CAF50;
                color: white;
            }

            .btn-primary:hover {
                background-color: #45a049;
            }

            .btn-secondary {
                background-color: #ccc;
                color: #333;
            }

            .btn-secondary:hover {
                background-color: #bbb;
            }

            .sms-history-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }

            .sms-history-table th,
            .sms-history-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }

            .sms-history-table th {
                background-color: #4CAF50;
                color: white;
            }

            .badge-sent {
                background-color: #4CAF50;
                color: white;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
        `;

        document.head.appendChild(style);
    }
}

// Global function to handle recipient type change
window.handleRecipientTypeChange = function() {
    const type = document.getElementById('recipient-type').value;
    const idGroup = document.getElementById('recipient-id-group');
    
    if (type === 'all') {
        idGroup.style.display = 'none';
    } else {
        idGroup.style.display = 'block';
    }
};

// Global function to handle message type change
window.handleMessageTypeChange = function() {
    const type = document.getElementById('message-type').value;
    const templateGroup = document.getElementById('template-group');
    const messageGroup = document.getElementById('message-group');
    
    if (type === 'template') {
        templateGroup.style.display = 'block';
        messageGroup.style.display = 'none';
    } else {
        templateGroup.style.display = 'none';
        messageGroup.style.display = 'block';
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.smsManager = new AdminSmsNotificationManager();
});
