# Frontend API Integration Guide (React Native / JS)

This document provides exact code examples for how to send and fetch data from the Falcon Delivery backend using JavaScript (`fetch` or `axios`). Make sure your backend is running at `http://127.0.0.1:8000` (or your local IP if testing on a physical device, like `http://192.168.x.x:8000`).

## 1. Setting up the Base URL
Create a configuration file or a constant for your API URL:
```javascript
// config.js
export const API_BASE_URL = 'http://127.0.0.1:8000/api';
// If testing on a real phone, use your computer's IP address instead:
// export const API_BASE_URL = 'http://192.168.1.50:8000/api';
```

---

## 2. Authentication: Logging In (Sending Data)
When logging in, you **send** an object to the server and **receive** a token.

### Using `fetch`:
```javascript
import { API_BASE_URL } from './config';

const login = async (email, password) => {
  try {
    const response = await fetch(`${API_BASE_URL}/auth/login`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      // This is how you send the object:
      body: JSON.stringify({
        email: email,
        password: password
      })
    });

    const data = await response.json();

    if (response.ok) {
      console.log('Login successful!', data.user);
      console.log('Your Token:', data.access_token);
      // Save this token in AsyncStorage or SecureStore!
      // await AsyncStorage.setItem('userToken', data.access_token);
      return data;
    } else {
      console.error('Login failed:', data);
    }
  } catch (error) {
    console.error('Error connecting to the server:', error);
  }
};
```

---

## 3. Fetching Data (GET Request with Token)
Once you have the token, you must include it in the `Authorization` header to fetch protected data.

### Example: Fetching User Profile
```javascript
import { API_BASE_URL } from './config';

const fetchProfile = async (token) => {
  try {
    const response = await fetch(`${API_BASE_URL}/profile`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}` // <--- IMPORTANT!
      }
    });

    const data = await response.json();

    if (response.ok) {
      console.log('User Profile Data:', data);
      return data;
    } else {
      console.error('Failed to fetch profile:', data);
    }
  } catch (error) {
    console.error('Error fetching data:', error);
  }
};
```

### Example: Fetching Restaurants List
```javascript
const fetchRestaurants = async (token) => {
  try {
    const response = await fetch(`${API_BASE_URL}/restaurants`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`
      }
    });

    const restaurants = await response.json();
    return restaurants;
  } catch (error) {
    console.error(error);
  }
};
```

---

## 4. Sending Complex Objects (Placing an Order)
Here is how you send a complex object containing an array of items.

```javascript
const placeOrder = async (token, restaurantId, addressId, cartItems) => {
  try {
    // cartItems looks like: [{ id: 1, qty: 2 }, { id: 2, qty: 1 }]
    
    const requestBody = {
      restaurant_id: restaurantId,
      address_id: addressId,
      items: cartItems
    };

    const response = await fetch(`${API_BASE_URL}/orders/create`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      // Convert the JavaScript object into a JSON string
      body: JSON.stringify(requestBody) 
    });

    const result = await response.json();

    if (response.ok) {
      console.log('Order placed successfully! Order ID:', result.orderId);
      return result;
    } else {
      console.error('Failed to place order:', result);
    }
  } catch (error) {
    console.error('Error placing order:', error);
  }
};
```

---

## 5. Summary Cheat Sheet
- **To send data (POST/PUT):** Use `method: 'POST'`, set `'Content-Type': 'application/json'`, and use `body: JSON.stringify(yourObject)`.
- **To fetch data (GET):** Use `method: 'GET'`. Do not send a `body`.
- **Authentication:** Always include `'Authorization': 'Bearer ' + token` in the `headers` for protected routes.
- **Handling responses:** Always use `response.json()` to parse the backend data back into a usable JavaScript object.
