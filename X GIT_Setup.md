# Git-Setup fuer {{TOOL_NAME}}

Remote: `{{GIT_REMOTE_URL}}`

```bash
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
