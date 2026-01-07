document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll(".header-nav a");
  const currentPath = window.location.pathname;
  links.forEach((link) => {
    if (link.getAttribute("href") === currentPath) {
      link.classList.add("is-active");
    }
  });

  const cartTrigger = document.querySelector("[data-cart-drawer-trigger]");
  const cartDrawer = document.querySelector("[data-cart-drawer]");
  const cartOverlay = document.querySelector("[data-cart-drawer-overlay]");
  const cartClose = document.querySelector("[data-cart-drawer-close]");
  const cartBody = document.querySelector("[data-cart-drawer-body]");
  const cartFooter = document.querySelector("[data-cart-drawer-footer]");
  const cartTotal = document.querySelector("[data-cart-drawer-total]");

  const openDrawer = () => {
    if (cartDrawer) {
      cartDrawer.removeAttribute("hidden");
      document.body.style.overflow = "hidden";
    }
  };

  const closeDrawer = () => {
    if (cartDrawer) {
      cartDrawer.setAttribute("hidden", "");
      document.body.style.overflow = "";
    }
  };

  const renderCart = (cart) => {
    if (!cartBody || !cartFooter || !cartTotal) return;
    if (cart.item_count === 0) {
      cartBody.innerHTML = "<p class=\"empty-state\">Your cart is empty.</p>";
      cartFooter.setAttribute("hidden", "");
      return;
    }

    const itemsMarkup = cart.items
      .map(
        (item) => `
          <div class="cart-item">
            <div>
              <p class="cart-item__title">${item.product_title}</p>
              <p class="cart-item__meta">${item.quantity} × ${item.price_formatted}</p>
            </div>
          </div>
        `
      )
      .join("");

    cartBody.innerHTML = itemsMarkup;
    cartTotal.textContent = cart.total_price_formatted;
    cartFooter.removeAttribute("hidden");
  };

  const formatMoney = (cents) => {
    if (window.Shopify && typeof Shopify.formatMoney === "function") {
      return Shopify.formatMoney(cents, Shopify.money_format);
    }
    return `$${(cents / 100).toFixed(2)}`;
  };

  const fetchCart = async () => {
    try {
      const response = await fetch("/cart.js");
      const cart = await response.json();
      renderCart({
        ...cart,
        total_price_formatted: formatMoney(cart.total_price),
        items: cart.items.map((item) => ({
          ...item,
          price_formatted: formatMoney(item.price),
        })),
      });
    } catch (error) {
      if (cartBody) {
        cartBody.innerHTML = "<p class=\"empty-state\">Unable to load cart.</p>";
      }
    }
  };

  if (cartTrigger) {
    cartTrigger.addEventListener("click", async () => {
      openDrawer();
      await fetchCart();
    });
  }
  if (cartOverlay) cartOverlay.addEventListener("click", closeDrawer);
  if (cartClose) cartClose.addEventListener("click", closeDrawer);
});
