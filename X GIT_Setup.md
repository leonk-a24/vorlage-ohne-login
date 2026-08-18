# Git-Setup fuer {{TOOL_NAME}}

Remote: `{{GIT_REMOTE_URL}}`

```bash
cd "C:\Pfad\zum\leeren Ordner"
git clone https://github.com/leonk-a24/vorlage-ohne-login.git .
Remove-Item -Recurse -Force .git

git init
git add .
git commit -m "Initial template setup"
git branch -M main
git remote add origin {{GIT_REMOTE_URL}}
git push -u origin main
```

Falls `origin` bereits existiert:

```bash
git remote set-url origin {{GIT_REMOTE_URL}}
git push -u origin main
```
