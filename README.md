# amc-activities
# AMC activities plugin for Wordpress

Extract activities from activities.outdoors.org for presentation in Wordpress websites
Bill Nesheim - February 2026

Usage:
[amc_activities
 chapter="AMC Boston Chapter",
 activities="Hiking, Local Walks, & Trail Running|Snowshoeing"
 keywords="list of keywords"
 audience="20's & 30's"
 events="6"
 length="30"
]

Supports both Classic and Block editor.
Chapters and activities may be abbreviated, although for best results use the defined names (or menus as presented in the block editor).
Length is the length of description to include. 
  if length > 0 whole paragraphs are presented, stopping after "length" words.
  to display only activity titles, dates and leaders specify length=0
Events is the # of events to include.
Audience should be one of the defined audiences from outdoor connector.
Keywords are arbitrary text.

The plugin caches activities from the Outdoor Connector, cache TTL is configurable in settings, as are default chapter, activity types and # of events to retrieve.

The plugin uses a Salesforce API directly, with no authentication, so outdoor connector changes to the API may break this plugin.
