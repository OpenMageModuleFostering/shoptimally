This module implement the "Featured Items" feature.

Featured Items is a feature that show the right items for the right client while browsing categories.
This feature works by listening to the event of loading category products, and injecting products into that collection that we get from our Shoptimally server.
There's also logic of prevent duplications and promoting existing products on page. The result is that the actual collection of products sent to render includes the featured items.