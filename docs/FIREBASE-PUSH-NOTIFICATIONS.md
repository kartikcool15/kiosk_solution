# Firebase Cloud Messaging - Setup Guide

## 🔥 Firebase Push Notifications Implementation

This WordPress theme now uses **Firebase Cloud Messaging (FCM)** for push notifications instead of Web Push API.

### ✅ Advantages of Firebase:
- **More Reliable**: Better delivery rates
- **Cross-Platform**: Works on web, Android, iOS
- **No OpenSSL Issues**: Firebase handles encryption
- **Better Analytics**: Built-in Firebase Analytics
- **Simpler Setup**: No VAPID key generation needed

---

## 📋 Setup Instructions

### Step 1: Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click **"Add project"**
3. Enter project name (e.g., "MP Online Notifications")
4. Disable Google Analytics (optional for push notifications)
5. Click **"Create project"**

### Step 2: Add Web App to Firebase

1. In your Firebase project, click the **web icon (</>)** to add a web app
2. Enter app nickname: "MP Online Web"
3. Check **"Also set up Firebase Hosting"** (optional)
4. Click **"Register app"**
5. You'll see Firebase configuration like this:

```javascript
const firebaseConfig = {
  apiKey: "AIzaSy...",
  authDomain: "your-project.firebaseapp.com",
  projectId: "your-project-id",
  storageBucket: "your-project.appspot.com",
  messagingSenderId: "123456789",
  appId: "1:123456789:web:abc123"
};
```

### Step 3: Enable Cloud Messaging

1. In Firebase Console, go to **Project Settings** (⚙️ icon)
2. Click on **"Cloud Messaging"** tab
3. Scroll to **"Web configuration"** section
4. Click **"Generate key pair"** to create VAPID key
5. Copy the **VAPID Key** (starts with "B...")
6. Copy the **Server key** (found at top of page under "Cloud Messaging API (Legacy)")

### Step 4: Configure WordPress

1. Go to **WordPress Admin → Settings → Firebase Notifications**
2. Fill in all the values from Firebase:
   - API Key
   - Auth Domain
   - Project ID
   - Storage Bucket
   - Messaging Sender ID
   - App ID
   - VAPID Key
   - Server Key
3. Click **"Save Settings"**

### Step 5: Update Service Worker

1. Open `/firebase-messaging-sw.js` in your site root
2. Replace the placeholder config with your actual Firebase config:

```javascript
firebase.initializeApp({
    apiKey: "YOUR_ACTUAL_API_KEY",
    authDomain: "your-project.firebaseapp.com",
    projectId: "your-project-id",
    storageBucket: "your-project.appspot.com",
    messagingSenderId: "123456789",
    appId: "1:123456789:web:abc123"
});
```

### Step 6: Add Notification Widget

1. Go to **Appearance → Widgets**
2. Find **"Firebase Notifications"** widget
3. Drag it to your sidebar
4. Configure title and description
5. Click **"Save"**

---

## 🧪 Testing

### Test Subscription:

1. Visit your website
2. You should see the notification widget in sidebar
3. Click **"Subscribe to Notifications"**
4. Browser will ask for permission - click **"Allow"**
5. You should see "✅ Subscribed to notifications"

### Test Notification:

1. Go to **WordPress Admin → Settings → Firebase Notifications**
2. Click **"Send Test Notification to All Subscribers"**
3. You should receive a notification (even if browser is in background)
4. Click the notification - it should open your website

### Test Post Publish:

1. Create a new post in WordPress
2. Publish it
3. All subscribers will automatically receive a notification
4. Notification will show post title and excerpt

---

## 📁 Files Created

### Frontend Files:
- `/firebase-messaging-sw.js` - Service worker for background notifications
- `/wp-content/themes/railways/assets/firebase-notifications.js` - Client-side JavaScript
- `/wp-content/themes/railways/assets/fcm-admin.css` - Admin styles
- `/wp-content/themes/railways/assets/fcm-admin.js` - Admin JavaScript

### Backend Files:
- `/wp-content/themes/railways/module/firebase-notifications/firebase-notifications.php` - Main PHP class
- `/wp-content/themes/railways/module/firebase-notifications/admin-page.php` - Admin settings page

### Database:
- Table: `wp_fcm_tokens` - Stores subscriber FCM tokens

---

## 🎯 Features

### Current Features:
✅ Subscribe/Unsubscribe functionality  
✅ Automatic notifications on post publish  
✅ Test notification from admin  
✅ Widget for easy subscription  
✅ Token management in database  
✅ Device info tracking  
✅ Foreground and background notifications  

### Future Enhancements (Infrastructure Ready):
🔲 Category-based subscriptions  
🔲 User preference management  
🔲 Scheduled notifications  
🔲 Analytics dashboard  
🔲 Custom notification templates  

---

## 🔧 Troubleshooting

### Notifications not working?

**Check 1: Firebase Configuration**
- Verify all Firebase config values are correct
- Make sure Server Key and VAPID Key are set

**Check 2: Service Worker**
- Open browser DevTools → Application → Service Workers
- Make sure `firebase-messaging-sw.js` is registered
- Click "Update" to reload service worker

**Check 3: Permissions**
- Open browser settings
- Check if notifications are allowed for your site
- In Chrome: chrome://settings/content/notifications

**Check 4: HTTPS**
- Push notifications require HTTPS (or localhost)
- Make sure your site is served over HTTPS in production

**Check 5: Browser Console**
- Open DevTools → Console
- Look for any Firebase errors
- Common error: "Firebase config not set" means service worker needs updating

### Database Issues?

If you see database errors, run this in phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS wp_fcm_tokens (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    token text NOT NULL,
    device_info text,
    categories text,
    subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_active datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY token_hash (token(191))
);
```

---

## 🔐 Security Notes

- **Server Key**: Keep this secret! Don't expose in client-side code
- **VAPID Key**: Public key, safe to expose in frontend
- **Tokens**: Store securely in database, never expose to users
- **Permissions**: Only admins can send test notifications

---

## 📊 Admin Features

### Settings Page:
- **Location**: Settings → Firebase Notifications
- **Features**:
  - Firebase configuration form
  - Subscriber count
  - Test notification button
  - Recent subscribers list
  - Setup instructions

### Widget:
- **Name**: Firebase Notifications
- **Location**: Appearance → Widgets
- **Customizable**:
  - Widget title
  - Description text

---

## 🚀 Going Live

Before deploying to production:

1. ✅ Test on localhost/staging
2. ✅ Verify Firebase config is for production project
3. ✅ Update service worker with production URL
4. ✅ Ensure site is served over HTTPS
5. ✅ Test on multiple browsers (Chrome, Firefox, Edge)
6. ✅ Test on mobile devices
7. ✅ Monitor subscriber count after launch

---

## 📞 Support

For issues with:
- **Firebase**: [Firebase Support](https://firebase.google.com/support)
- **WordPress Integration**: Check debug.log in wp-content/
- **Browser Issues**: Check browser console for errors

---

**Version**: 1.0.0  
**Last Updated**: March 2, 2026  
**Requires**: WordPress 5.0+, PHP 7.2+, HTTPS
