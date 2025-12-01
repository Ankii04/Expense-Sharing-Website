# Quick Start: Deploy to Railway

## ✅ Files Ready for Deployment
- `.gitignore` - Protects sensitive files
- `railway.json` - Railway configuration  
- `config-production.php` - Production database config

## 🚀 Deploy in 5 Steps

### 1. Push to GitHub
```bash
cd "C:\Users\HP\OneDrive\Desktop\Sem 4 Projects\expense-maker"
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/YOUR_USERNAME/expense-maker.git
git push -u origin main
```

### 2. Deploy on Railway
- Go to [railway.app](https://railway.app)
- Login with GitHub
- Click "New Project" → "Deploy from GitHub repo"
- Select `expense-maker`

### 3. Add MySQL Database
- Click "+ New" → "Database" → "Add MySQL"
- Railway auto-configures connection

### 4. Import Database
- Click MySQL service → "Data" tab
- Copy/paste `complete_setup.sql` content
- Click "Run"

### 5. Get Your URL
- Click web service → "Settings"
- Copy your Railway URL
- Visit and test!

## 📖 Full Guide
See [deployment-guide.md](file:///C:/Users/HP/.gemini/antigravity/brain/c339a426-187d-4966-8dd4-7a84643793f8/deployment-guide.md) for detailed instructions.

## 🆓 Cost
**FREE** - Railway gives $5 credit/month (enough for small projects)
