import { getContext, store } from '@wordpress/interactivity';
import '@woocommerce/stores/woocommerce/products';
import '@woocommerce/stores/woocommerce/cart';

const universalLock =
    'I acknowledge that using a private store means my plugin will inevitably break on the next store release.';

const { state: productsState } = store(
    'woocommerce/products',
    {},
    { lock: universalLock }
);

const wooStore = store('woocommerce', {}, { lock: universalLock });
const wooState = wooStore.state;
const wooActions = wooStore.actions;

function readCookie(name) {
    const prefix = `${ name }=`;
    const parts = document.cookie ? document.cookie.split(';') : [];
    for (const part of parts) {
        const value = part.trim();
        if (value.indexOf(prefix) === 0) {
            return decodeURIComponent(value.slice(prefix.length));
        }
    }
    return '';
}

function writeDecorationCookie(productId, decoration) {
    if (!productId || !decoration) return;
    document.cookie = `asbo_matrix_decoration_${ productId }=${ encodeURIComponent(decoration) }; path=/; max-age=7200; SameSite=Lax`;
}

function resolveDecoration(context) {
    if (!context.hasMatrix) return '';

    const productId = Number(context.parentProductId || 0);
    const fromCookie = readCookie(`asbo_matrix_decoration_${ productId }`);
    if (fromCookie) return fromCookie;

    const matrix = document.querySelector(`[data-asbo-matrix-product="${ productId }"]`);
    const fromMatrix = matrix ? matrix.getAttribute('data-default-decoration') : '';
    return fromMatrix || context.decoration || '';
}

function selectedAttributes() {
    const wooContext = getContext('woocommerce/add-to-cart-with-options');
    return wooContext && Array.isArray(wooContext.selectedAttributes)
        ? wooContext.selectedAttributes
        : [];
}

function currentProduct() {
    const main = productsState.mainProductInContext;
    if (!main) return null;

    if (main.type === 'variable') {
        return productsState.productVariationInContext || null;
    }

    return productsState.productVariationInContext || main;
}

function productReady(product) {
    return !!(
        product &&
        product.type !== 'variable' &&
        product.is_purchasable !== false &&
        product.is_in_stock !== false
    );
}

function itemDecoration(item) {
    return (
        item &&
        item.extensions &&
        item.extensions.asbo_matrix &&
        item.extensions.asbo_matrix.decoration
    ) || '';
}

function matchingItem(context, product) {
    if (!product || !wooState.cart || !Array.isArray(wooState.cart.items)) {
        return null;
    }

    const decoration = context.hasMatrix ? resolveDecoration(context) : '';

    return wooState.cart.items.find((item) => {
        if (Number(item.id) !== Number(product.id)) return false;

        if (context.hasMatrix) {
            return !!decoration && itemDecoration(item) === decoration;
        }

        return true;
    }) || null;
}

function maxQuantity(product) {
    const maximum = product && product.add_to_cart
        ? Number(product.add_to_cart.maximum)
        : NaN;
    return Number.isFinite(maximum) ? maximum : Infinity;
}

function* commitQuantity(context, desired) {
    const product = currentProduct();
    if (!productReady(product) || context.busy) return;

    const safeDesired = Math.max(0, Math.min(maxQuantity(product), Number(desired) || 0));
    const item = matchingItem(context, product);
    const variation = selectedAttributes();

    context.busy = true;
    context.error = '';
    context.optimisticQuantity = safeDesired;

    try {
        if (context.hasMatrix) {
            context.decoration = resolveDecoration(context);
            writeDecorationCookie(context.parentProductId, context.decoration);
        }

        if (safeDesired <= 0) {
            if (item && item.key) {
                yield wooActions.removeCartItem(item.key);
            }
        } else if (item && item.key) {
            const outcome = yield wooActions.addCartItem(
                {
                    id: product.id,
                    key: item.key,
                    quantity: safeDesired,
                    variation,
                    type: product.type,
                },
                { showCartUpdatesNotices: false }
            );

            if (outcome && outcome.success === false) {
                context.error = outcome.error && outcome.error.message
                    ? outcome.error.message
                    : 'Unable to update the cart.';
            }
        } else {
            const outcome = yield wooActions.addCartItem(
                {
                    id: product.id,
                    quantityToAdd: safeDesired,
                    variation,
                    type: product.type,
                },
                { showCartUpdatesNotices: false }
            );

            if (outcome && outcome.success === false) {
                context.error = outcome.error && outcome.error.message
                    ? outcome.error.message
                    : 'Unable to update the cart.';
            }
        }

        yield wooActions.waitForIdle();
    } catch (error) {
        context.error = error && error.message
            ? error.message
            : 'Unable to update the cart.';
    } finally {
        context.busy = false;
        context.optimisticQuantity = null;
    }
}

const { state, actions } = store('asbo-matrix/quantity', {
    state: {
        get quantity() {
            const context = getContext();
            if (context.optimisticQuantity !== null && context.optimisticQuantity !== undefined) {
                return Number(context.optimisticQuantity) || 0;
            }

            const product = currentProduct();
            const item = matchingItem(context, product);
            return item ? Number(item.quantity || 0) : 0;
        },

        get isBusy() {
            return !!getContext().busy;
        },

        get hint() {
            const context = getContext();
            const main = productsState.mainProductInContext;
            const product = currentProduct();

            if (!main) return 'Unavailable';
            if (main.type === 'variable' && !productsState.productVariationInContext) {
                return 'Choose options first';
            }
            if (product && product.is_in_stock === false) return 'Out of stock';
            if (context.error) return context.error;
            return '';
        },

        get hideHint() {
            return !state.hint;
        },

        get status() {
            const context = getContext();
            if (context.busy) return 'Updating cart';
            if (context.error) return context.error;
            return `${ state.quantity } in cart`;
        },

        get disableDecrease() {
            const context = getContext();
            return !!context.busy || !productReady(currentProduct()) || state.quantity <= 0;
        },

        get disableIncrease() {
            const context = getContext();
            const product = currentProduct();
            return !!context.busy || !productReady(product) || state.quantity >= maxQuantity(product);
        },
    },

    actions: {
        *increase() {
            const context = getContext();
            if (state.disableIncrease) return;
            yield* commitQuantity(context, state.quantity + 1);
        },

        *decrease() {
            const context = getContext();
            if (state.disableDecrease) return;
            yield* commitQuantity(context, state.quantity - 1);
        },
    },

    callbacks: {
        init() {
            const context = getContext();
            context.decoration = resolveDecoration(context);
            if (context.hasMatrix && context.decoration) {
                writeDecorationCookie(context.parentProductId, context.decoration);
            }

            const handler = (event) => {
                const detail = event && event.detail ? event.detail : {};
                if (Number(detail.productId) !== Number(context.parentProductId)) return;
                context.decoration = detail.decoration || '';
                context.optimisticQuantity = null;
            };

            document.addEventListener('asbo-matrix:decoration-change', handler);
            return () => document.removeEventListener('asbo-matrix:decoration-change', handler);
        },
    },
});
