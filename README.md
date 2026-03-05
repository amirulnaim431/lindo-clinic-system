# Lindo Clinic System (Laravel)

Internal clinic operations system for Lindo Clinic.

This repository is deployed via Git to Plesk staging (no direct edits in Plesk File Manager).
- Repo: lindo-clinic-system
- Branches:
  - `staging` = staging server deploy target
  - `main` = production-ready (promoted later)

---

## Environments & Deployment

### Local development
Work in your local project folder (example):
- `C:\laragon\www\lindo-app`

Workflow:
1) Make changes locally
2) Commit to Git
3) Push to GitHub `staging`
4) Plesk pulls `staging` and deploys

Commands:
```bash
git status
git add .
git commit -m "your message"
git push origin staging