# Catalyst SDK - Integration Documentation

This documentation details how to integrate and use the Catalyst SDK in your Landing Pages. The SDK automatically manages visitor identification and provides an event-based interface for lead registration and updates.

## 1. Installation

Insert the following snippet into the `<head>` of your HTML.
> **Important:** When using this method, you must manually configure the API URL in the `config` object.
> **Note:** If you are using Astro, make sure to add the `is:inline` attribute to the `<script>` tag.

```html
<script>
(function(w,d,s,u,c){
  w.Catalyst=w.Catalyst||{_q:[],config:c};
  // Proxy for methods that return Promises
  ['registerLead','updateLead','getOfferwall','convertOfferwall'].forEach(function(m){
    w.Catalyst[m]=function(){
      var a=arguments;
      return new Promise(function(resolve, reject){
        w.Catalyst._q.push([m, a, resolve, reject]);
      })
    };
  });
  // Proxy for void methods
  ['on','dispatch'].forEach(function(m){
    w.Catalyst[m]=function(){w.Catalyst._q.push([m].concat([].slice.call(arguments)))};
  });
  
  var j=d.createElement(s),f=d.getElementsByTagName(s)[0];
  j.async=1;j.type='module';j.src=u;f.parentNode.insertBefore(j,f);
})(window,document,'script','https://your-domain.com/catalyst/v1.0.js',{
  api_url:'https://api.your-domain.com',debug:true
});
</script>
```

### Summary of Differences

| Feature | Option A (Loader) | Option B (Manual) |
| :--- | :--- | :--- |
| **Script** | `/engine.js` | `/v1.0.js` (Direct) |
| **Configuration** | Automatic (Injected by server) | Manual (In HTML) |
| **Ideal Use** | Standard Landings, Laravel Blade | SPAs, WordPress, Static Sites |

---

## 2. Lifecycle and Events

The SDK operates with an event-driven architecture (Pub/Sub). You don't call synchronous methods; instead, you listen for events or dispatch actions.

### Event: `ready`
Fired once when the SDK has loaded AND the visitor session is confirmed (either retrieved from local cache or registered via API).

You must **always** wrap your logic within this listener.

```javascript
Catalyst.on('ready', function(eventData) {
    console.log('SDK Ready. Visitor data:', eventData.visitor);
    // It is now safe to attempt to register leads
});
```

### Event: `lead:status`
This is the unified event to know the result of your operations (registration or update).

Event structure:
```typescript
{
    type: 'register' | 'update', // Which operation was attempted
    success: boolean,            // Whether it worked or not
    data?: object,               // Data returned by the API (if success: true)
    error?: string               // Error message (if success: false)
}
```

---

## 3. Available Actions (Event Mode)

To interact with the SDK, use `Catalyst.dispatch(actionName, payload)`.

### A. Register a Lead (`lead:register`)
Sends form data to convert it into a Lead associated with the current visitor.

```javascript
Catalyst.dispatch('lead:register', {
    name: 'John Doe',
    email: 'john@example.com',
    phone: '+1 555 555 5555',
    custom_field: 'value'
});
```

### B. Update a Lead (`lead:update`)
Adds or modifies information for an already registered lead.
> **Important:** This action will fail if the visitor has not been previously registered as a lead.

```javascript
Catalyst.dispatch('lead:update', {
    company: 'Company Inc.',
    role: 'Manager'
});
```

---

## 4. Advanced Usage: Async/Await

If you prefer stricter or more modern flow control, you can directly invoke SDK methods instead of using `dispatch`. These methods return a `Promise`.

> **Note:** To use this, you must first ensure the SDK is loaded (using the initial `ready` event).

### Register Lead (Async)

```javascript
Catalyst.on('ready', async () => {
    try {
        // Wait for the response directly
        await Catalyst.registerLead({
            email: 'test@example.com',
            name: 'Test User'
        });
        console.log('Lead registered successfully (Async)');

        // Now it is safe to update
        await Catalyst.updateLead({ role: 'Admin' });
        console.log('Lead updated successfully (Async)');

    } catch (error) {
        console.error('There was an error in the async flow:', error);
    }
});
```

This approach is ideal if you need to perform complex validations or dependent action chains without nesting multiple event listeners.

---

## 5. Offerwall (NEW)

The SDK includes support for loading Offerwalls and registering conversions directly.

### A. Get Offerwall (`getOfferwall`)

Retrieves the list of available offers for the current visitor based on an Offerwall Mix ID. You can optionally specify a `placement` to indicate where on the page it is shown (useful for reporting).

> **Note:** This function returns a Promise, so you can use `await`. Make sure the SDK is loaded (`ready` event) before calling it, or use promise syntax.

```javascript
// Inside an async function
const mixId = '123'; // ID of your Offerwall Mix
const placement = 'thank_you_page'; // Optional: 'popup', 'sidebar', etc.

try {
  const response = await Catalyst.getOfferwall(mixId, placement);
  console.log('Offers:', response.data);
} catch (error) {
  console.error('Error loading offerwall:', error);
}
```

### B. Register Conversion (`convertOfferwall`)

Registers that the user has completed an offer (conversion). It is crucial to send the `offer_token` you received when fetching offers (`getOfferwall`), as it contains encrypted information to validate the conversion.

```javascript
// Assume 'offer' is one of the objects received in getOfferwall()
const selectedOffer = offers[0]; 

try {
  const conversion = await Catalyst.convertOfferwall({
    offer_token: selectedOffer.offer_token, // REQUIRED: Comes from the offer object
    amount: 10.50,                          // Optional: Conversion value
    click_id: 'click_12345',                // Optional: External ID if you have one
    utm_source: 'facebook',                 // Optional
    utm_medium: 'cpc'                       // Optional
  });
  console.log('Conversion registered successfully:', conversion);
} catch (error) {
  console.error('Error registering conversion:', error);
}
```

> **Important:** The `offer_token` is dynamically generated by the backend and is unique to that impression of the offer. Do not attempt to modify or reuse it between sessions.

---

## 6. Frequently Asked Questions (FAQ)

### Why aren't my Lead changes saved if I call `update` immediately?
`lead:update` requires the visitor to already have an associated "Lead ID" in the backend. If you trigger `lead:register` and `lead:update` simultaneously (or very quickly), the update might arrive before the registration finishes.
**Solution:** Use the event approach waiting for `lead:status` (type: 'register', success: true) OR use `await Catalyst.registerLead(...)` before updating.

### Does every page reload count as a new visit?
**No.** The SDK implements a 15-minute "Throttle" system using `localStorage` and Cookies.
- If a user reloads the page within 15 minutes, the SDK retrieves the session locally (without calling the API).
- The `ready` event fires just as quickly, but with cached data.

### What happens if the API fails to start (Error 500)?
The SDK will capture the error internally and show it in the console if `debug: true` is active. The `ready` event will **NOT** fire, preventing you from attempting to register leads without a valid session.

### Where can I see debug logs?
Make sure to pass `{ debug: true }` in the second argument of the initial loader configuration. This will print every fired event and every API response to the browser console.

### Is the visit registered immediately?
**Yes.** As soon as the snippet loads in the browser, the SDK contacts the API to register the visit (or retrieve the existing session) and initialize tracking. This happens automatically before the `ready` event is fired.

### Should I register the lead automatically when the page loads?
Generally, **NO**. Lead registration should be a voluntary action by the user, such as clicking a "Submit" or "Register" button.

**Exception:** In "All-in-One" Landing Pages or flows where the goal is to show an Offerwall immediately, you may register the lead at the start (inside the `ready` event) to unlock offers right away.
