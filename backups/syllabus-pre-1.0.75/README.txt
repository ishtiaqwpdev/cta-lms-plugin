CTA LMS syllabus sync backup notes (v1.0.75)
============================================

Before syllabus sync runs on a site, CTA_Syllabus_Sync stores a JSON snapshot of
matching courses + modules in the WordPress option:

  cta_syllabus_backup_1_0_75

Sync report is stored in:

  cta_syllabus_synced_1_0_75

To re-run sync after deploy (WP-CLI or code):

  delete_option( 'cta_syllabus_synced_1_0_75' );
  CTA_Syllabus_Sync::sync_all( true );

Plugin source files changed for this release are version-controlled in git.
Database mutations happen only when the plugin upgrades to 1.0.75 on a live site.
