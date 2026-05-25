// ============================================================
//  Trainee SMS Notification Display
//  pages/trainee/trainee_sms_notifications.js
//  
//  Display SMS notifications for trainees
// ============================================================

class TraineeSmsNotifications {
    constructor() {
        this.baseUrl = '/pages/trainee';
        this.messages = [];
        this.init();
    }

    init() {
        this.loadMessages();
        // Refresh every 30 seconds
        setInterval(() => this.loadMessages(), 30000);
    }

    /**
     * Load SMS messages
     */
    async loadMessages(limit = 50) {
        try {
            const response = await fetch(`${this.baseUrl}/get_sms_notifications.php?limit=${limit}`);
            const result = await response.json();

            if (result.success) {
                this.messages = result.messages;
                this.displayMessages();
            }
        } catch (error) {
            console.error('Failed to load SMS messages:', error);
        }
    }

    /**
     * Display SMS messages
     */
    displayMessages() {
        const container = document.getElementById('sms-notifications-container');
        if (!container) return;

        let html = '';

        if (this.messages.length === 0) {
            html = '<div class="empty-state"><p>No SMS messages yet.</p></div>';
        } else {
            html = '<div class="sms-messages-list">';
            
            this.messages.forEach(msg => {
                const date = new Date(msg.received_at).toLocaleString();
                const typeClass = msg.notification_type ? `badge-${msg.notification_type}` : 'badge-default';
                
                html += `
                    <div class="sms-message-card ${msg.is_read ? '' : 'unread'}">
                        <div class="message-header">
                            <span class="type-badge ${typeClass}">${msg.notification_type || 'Message'}</span>
                            <span class="timestamp">${date}</span>
                        </div>
                        <div class="message-body">
                            <p>${this.escapeHtml(msg.message)}</p>
                        </div>
                        <div class="message-footer">
                            <small>From: ${msg.phone_number}</small>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
        }

        container.innerHTML = html;
        this.addStyles();
    }

    /**
     * Add CSS styles for SMS display
     */
    addStyles() {
        if (document.getElementById('sms-notifications-styles')) return;

        const style = document.createElement('style');
        style.id = 'sms-notifications-styles';
        style.textContent = `
            #sms-notifications-container {
                padding: 20px;
            }

            .sms-messages-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 15px;
            }

            .sms-message-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
            }

            .sms-message-card:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transform: translateY(-2px);
            }

            .sms-message-card.unread {
                border-left: 4px solid #4CAF50;
                background-color: #f0f8f4;
            }

            .message-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }

            .type-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                color: white;
            }

            .badge-order_placed {
                background-color: #2196F3;
            }

            .badge-order_shipped {
                background-color: #FF9800;
            }

            .badge-order_delivered {
                background-color: #4CAF50;
            }

            .badge-workshop_enrollment {
                background-color: #9C27B0;
            }

            .badge-workshop_reminder {
                background-color: #F44336;
            }

            .badge-certification_issued {
                background-color: #00BCD4;
            }

            .badge-default {
                background-color: #757575;
            }

            .timestamp {
                font-size: 12px;
                color: #999;
            }

            .message-body {
                margin: 12px 0;
                line-height: 1.5;
                color: #333;
                word-wrap: break-word;
            }

            .message-body p {
                margin: 0;
            }

            .message-footer {
                padding-top: 10px;
                border-top: 1px solid #eee;
                color: #999;
            }

            .empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #999;
            }

            .empty-state p {
                margin: 0;
                font-size: 16px;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('sms-notifications-container')) {
        window.smsNotifications = new TraineeSmsNotifications();
    }
});
