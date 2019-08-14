This module is just like the "Featured Items" feature, but designed to work with cached pages via ajax.

It works like this:
1. We inject page data from magento to the JavaScript client.
2. The JavaScript query featured items ids from our Shoptimally server when page is loaded.
3. We then use the ids and get the html used to render those products from an API we open in magento (a special route that gets product id and return html).
4. The JavaScript inject the featured items into the page.