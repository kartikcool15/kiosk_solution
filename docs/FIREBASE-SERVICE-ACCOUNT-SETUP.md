# How to Get Firebase Service Account JSON

## ⚠️ Important: Legacy Server Key is Deprecated

Firebase has disabled the **Cloud Messaging API (Legacy)** which used server keys. You now need to use the **FCM HTTP v1 API** with a **Service Account**.

---

## Steps to Get Service Account JSON

### 1. Open Firebase Console
- Go to [Firebase Console](https://console.firebase.google.com/)
- Select your project (govtjobsexams-ec4ac)

### 2. Access Service Accounts
1. Click the **⚙️ (Settings)** icon in the left sidebar
2. Click **"Project settings"**
3. Click the **"Service accounts"** tab

### 3. Generate Private Key
1. You'll see your service account email (looks like: `firebase-adminsdk-xxxxx@yourproject.iam.gserviceaccount.com`)
2. Click the button **"Generate new private key"**
3. A dialog will appear warning you to keep this key secure
4. Click **"Generate key"**
5. A JSON file will download to your computer (e.g., `govtjobsexams-ec4ac-firebase-adminsdk-xxxxx.json`)

### 4. Get the JSON Contents
1. Open the downloaded JSON file in a text editor (Notepad, VS Code, etc.)
2. The file will look like this:

```json
{
  "type": "service_account",
  "project_id": "govtjobsexams-ec4ac",
  "private_key_id": "abc123...",
  "private_key": "-----BEGIN PRIVATE KEY-----\nMIIEvQIBA...\n-----END PRIVATE KEY-----\n",
  "client_email": "firebase-adminsdk-xxxxx@govtjobsexams-ec4ac.iam.gserviceaccount.com",
  "client_id": "123456789...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/..."
}
```

3. **Select ALL** the content (Ctrl+A / Cmd+A)
4. **Copy** it (Ctrl+C / Cmd+C)

### 5. Paste in WordPress

1. Go to your WordPress admin
2. Navigate to **Settings → Firebase Notifications**
3. Scroll down to **"Service Account JSON"** field
4. Paste the entire JSON content
5. Click **"Save Settings"**

---

## ✅ What You Should Have Now

In **Settings → Firebase Notifications**, these fields should be filled:

| Field | Example Value |
|-------|---------------|
| API Key | `AIzaSyBkhedcvOYzuFYbQHDVKppTH9B4HfMC2-Y` |
| Auth Domain | `govtjobsexams-ec4ac.firebaseapp.com` |
| Project ID | `govtjobsexams-ec4ac` |
| Storage Bucket | `govtjobsexams-ec4ac.firebasestorage.app` |
| Messaging Sender ID | `31996403157` |
| App ID | `1:31996403157:web:cb659268fe99cb7705315c` |
| VAPID Key | Get from Cloud Messaging → Web Push certificates |
| **Service Account JSON** | **Entire JSON from downloaded file** |

---

## 🔒 Security Notes

⚠️ **IMPORTANT**: The service account JSON contains your private key!

- **Never commit** this to Git/GitHub
- **Never share** it publicly
- **Keep it secret** - it has admin access to your Firebase project
- If leaked, immediately go to Firebase Console → Service Accounts → Delete the key → Generate a new one

---

## 🧪 Test It

After saving:

1. Visit your website
2. Click "Subscribe to Notifications" in the sidebar
3. Go to WordPress admin → Settings → Firebase Notifications
4. Click "Send Test Notification to All Subscribers"
5. You should receive a notification! 🎉

---

## ❓ Troubleshooting

### "Failed to get access token"
- Check that the Service Account JSON is valid (proper JSON format)
- Make sure you copied **all** of it including the `{` and `}`

### "Project ID not configured"
- Make sure the Project ID field is filled in the settings

### Still not working?
- Check WordPress debug log: `/wp-content/debug.log`
- Look for lines starting with "FCM:"
- Make sure OpenSSL PHP extension is enabled (for JWT signing)
