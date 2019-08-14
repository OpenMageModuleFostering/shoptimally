Full page sort is a feature that actually change all the products on page, eg replace the original products with alternative ones.

It works like this:
1. We inject another products grid right above the original products grid (so original grid still exist). All the products in the full-sort grid are placeholders with "loading..." graphics.
2. We inject a javascript snippet that hide the original grid (before page load so no flickering), but also add a timer to show it again in case Shoptimally times out.
3. When Shoptimally JavaScript loads and feature runs, it will start replacing the products placeholders with real products from Shoptimally.
3. 1. Shoptimally will send product ids, and the js will use a special URL this module creates to convert them to htmls.
4. After feature runs the timer to show the original products will be disabled, so the original products will never be shown.