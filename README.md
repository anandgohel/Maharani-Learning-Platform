# LearnDash MW - Maharani Weddings Learning Platform

Custom WordPress plugin for learn.maharaniweddings.com analytics, Salesforce completion tracking, and user surveys.

## Project Structure

```
LearnDash_MW/
├── .ssh/                    # SSH keys (gitignored)
├── mw-learndash-analytics/  # Custom WordPress plugin
│   ├── mw-learndash-analytics.php
│   ├── includes/
│   ├── admin/
│   ├── assets/
│   └── templates/
└── ssh_connect.py           # SSH deployment utility
```

## Deployment

Plugin is developed locally and deployed to SiteGround via SSH/SCP:
- **Host:** gcam1167.siteground.biz
- **Path:** ~/www/learn.maharaniweddings.com/public_html/wp-content/plugins/

## Rollback

1. **Plugin deactivation:** Deactivate via WP Admin → instant revert
2. **Full rollback:** Restore from `/home/customer/wp-content-backup-20260520.tar.gz`
3. **SiteGround backups:** Site Tools → Security → Backups
