<nav aria-label="মোবাইল শর্টকাট" class="mobile-bottom-nav">
    <a data-route="home" href="{{ route('home') }}"><i aria-hidden="true" class="fa-solid fa-house"></i><span>HOME</span></a>
    <button data-route="menu" id="mobileBottomMenu" type="button"><i aria-hidden="true" class="fa-solid fa-bars"></i><span>MENU</span></button>
    <a aria-label="কার্ট খুলুন" data-cart-drawer-open data-route="cart" href="{{ route('cart.index') }}"><i aria-hidden="true" class="fa-solid fa-cart-shopping"></i><span class="bottom-cart-count" id="bottomCartCount">{{ $cartCount }}</span><span>CART</span></a>
    <button data-route="search" id="mobileBottomSearch" type="button"><i aria-hidden="true" class="fa-solid fa-magnifying-glass"></i><span>SEARCH</span></button>
    <a data-route="account" href="{{ auth()->check() ? route('account') : route('login') }}"><i aria-hidden="true" class="fa-solid fa-user"></i><span>ACCOUNT</span></a>
</nav>
