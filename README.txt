Tweet Your MP creates a block which allows a user to enter a UK postcode and using the They Work For You API, retrieve an MP's constituency details and from that either generate a Tweet or Email button.

To configure:

A They Work For You API key must be generated at http://www.theyworkforyou.com/api/

On the Basic Settings admin page, save the API key and the default tweet text.

Once the module has been enabled, the MP data must be imported to the tweet_your_mp table. Note that the csv headers MUST remain in place. Go to admin/config/services/tweet_your_mp/import, browse for the csv and click 'Import File'.

Once imported, the MP details can be listed admin/config/services/tweet_your_mp/list from where each can be edited.

#TODO
Tokenise final message to allow user to include MP website / constituency office etc.
Tokenise initial tweet message
MP edit form validation