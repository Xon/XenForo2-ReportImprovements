# Report Improvements

A large collection of improvements to XenForo's reporting system.

Note; when reports are sent to a forum, the warning<->report links can not be created!

- Improve performance of Report Center by reducing stock XenForo N+1 query behaviour
- Permission based access to the report center:
 - Default permissions set for groups (global/content) for groups with warn or edit basic profile permissions.
 - New Permissions:
   - View Report center.
   - Comment on open report
   - Comment on closed report
   - Update a report's status
   - Assign report
   - See reporter username 
- Sends an Alert to moderators who have commented/reported on a report.
  - Only sends an alert if the previous alert has not been viewed.
  - Report Alerts link to the actual comments for longer reports.
  - Report Alerts include the title of the report.
- Links Warnings to reports.
  - Visible from the warning itself, and when issuing warnings against content.
- Link reply bans to reports
  - Log reply bans into report system
  - Optional Issue a reply-ban on issuing a warning (default disabled)
- Link Reports to Warnings.
  - Logs changes to Warnings (add/edit/delete), and associates them with a report.
- Automatically create a report for a warning.
- When issuing a Warning, option to resolve any linked report.
- Optional ability to log warnings into reports when they expire. This does not disrupt who the report was assigned to, and does not re-open the report.
- Report Comment Likes.
- Resolved Report Alerts are logged into Report Comments (as an explicit field).
- Search report comments
- Optional ability to search report comments by associated warning points, and warned user. (Requires Enhanced Search Improvements add-on)
- Reverse order of report comments (default disabled)
- Optional auto-reject/resolve sufficiently old reports (default disabled)
- Show content date when viewing a report
- Show forum for post reports in report list