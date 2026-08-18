cd "C:\Pfad\zum\leeren Ordner"

git clone https://github.com/leonk-a24/tool-vorlage.git .

Remove-Item -Recurse -Force .git
git init
git add .
git commit -m "Projekt aus Vorlage erstellt"
git branch -M main

git remote add origin https://github.com/leonk-a24/mein-neues-tool.git
git push -u origin main