# Firebase Push Notifications - Quick Setup Checklist

## ✅ Setup Checklist (Complete in Order)

### 1. Firebase Console Setup
- [ ] Create Firebase project at https://console.firebase.google.com/
- [ ] Add web app to Firebase project
- [ ] Copy Firebase configuration (apiKey, authDomain, etc.)
- [ ] Enable Cloud Messaging
- [ ] Generate VAPID key (Web Push certificates)
- [ ] Copy Server key (Legacy)

### 2. WordPress Configuration
- [ ] Go to **Settings → Firebase Notifications** in WordPress Admin
- [ ] Paste all Firebase config values
- [ ] Click "Save Settings"

### 3. Update Service Worker
- [ ] Open `/firebase-messaging-sw.js` in site root
- [ ] Replace placeholder config with your Firebase config
- [ ] Save file

### 4. Add Widget
- [ ] Go to **Appearance → Widgets**
- [ ] Add "Firebase Notifications" widget to sidebar
- [ ] Save

### 5. Test
- [ ] Visit your website
- [ ] Click "Subscribe to Notifications" in widget
- [ ] Allow notifications when prompted
- [ ] Go to **Settings → Firebase Notifications**
- [ ] Click "Send Test Notification"
- [ ] Verify you receive the notification

### 6. Test Post Publish
- [ ] Create and publish a new post
- [ ] Verify notification is sent automatically

---

## 🔥 Firebase Config Template

Copy this to `firebase-messaging-sw.js` (replace with your actual values):

```javascript
firebase.initializeApp({
    apiKey: "PASTE_YOUR_API_KEY_HERE",
    authDomain: "your-project-id.firebaseapp.com",
    projectId: "your-project-id",
    storageBucket: "your-project-id.appspot.com",
    messagingSenderId: "123456789",
    appId: "1:123456789:web:abc123def456"
});
```

---

## 📍 Where to Find Firebase Values

| Value | Where to Find It |
|-------|------------------|
| API Key | Firebase Console → Project Settings → General → Web API Key |
| Auth Domain | Firebase Console → Project Settings → General (auto: project-id.firebaseapp.com) |
| Project ID | Firebase Console → Project Settings → General |
| Storage Bucket | Firebase Console → Project Settings → General (auto: project-id.appspot.com) |
| Messaging Sender ID | Firebase Console → Project Settings → Cloud Messaging → Sender ID |
| App ID | Firebase Console → Project Settings → General → Your apps → App ID |
| VAPID Key | Firebase Console → Project Settings → Cloud Messaging → Web Push certificates |
| Server Key | Firebase Console → Project Settings → Cloud Messaging → Server key |

---

## 🚨 Common Issues

### Issue: "Firebase not initialized"
**Solution**: Update `/firebase-messaging-sw.js` with your Firebase config

### Issue: "Notifications not received"
**Solution**: 
1. Check browser notifications are allowed
2. Verify Server Key is set in WordPress settings
3. Check browser console for errors

### Issue: "Service Worker not registering"
**Solution**:
1. Make sure file is at `/firebase-messaging-sw.js` (site root)
2. Clear browser cache
3. Open DevTools → Application → Service Workers → Unregister → Reload

---

## 📞 Need Help?

1. Read full documentation: `/docs/FIREBASE-PUSH-NOTIFICATIONS.md`
2. Check browser console for errors
3. Check WordPress debug.log: `/wp-content/debug.log`
4. Visit Firebase documentation: https://firebase.google.com/docs/cloud-messaging
