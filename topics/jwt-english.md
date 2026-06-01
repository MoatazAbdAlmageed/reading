---
title: Introduction to JSON Web Tokens (JWT)
lang: en
date: 2026-06-01 13:03:00
---
**JSON Web Token (JWT)**, pronounced "jot," is an open standard (RFC 7519) that defines a compact, self-contained, and URL-safe method for securely transmitting information between two parties as a JSON object. It is widely used in modern web applications and APIs for authentication, authorization, and secure information exchange.

---

### 🧱 Structure of a JWT

A JWT consists of three parts separated by dots (`.`), which are Base64Url encoded. The structure looks like this: `header.payload.signature`.

1.  **Header**: Contains metadata about the token. It typically specifies the token type (`typ`, usually "JWT") and the cryptographic algorithm (`alg`) used to sign the token, such as HMAC SHA-256 (HS256) or RSA (RS256).
2.  **Payload (Claims)**: Contains the actual data being transmitted, which are called "claims". Claims are statements about an entity (like a user) and can be:
    *   **Registered Claims**: Standardized and recommended fields like `iss` (issuer), `sub` (subject), `aud` (audience), and `exp` (expiration time).
    *   **Public Claims**: Custom claims that should be defined in the IANA registry or as collision-resistant URIs.
    *   **Private Claims**: Custom data agreed upon by the specific parties exchanging the token, such as user roles.
3.  **Signature**: Created by taking the encoded header, encoded payload, and a secret or private key, and passing them through the algorithm specified in the header. The signature verifies that the sender is who they claim to be and ensures the token's data has not been tampered with in transit.

---

### 🔄 How JWT Authentication Works

1.  **Login**: A user submits their credentials (e.g., username and password) to the server.
2.  **Token Issuance**: Once verified, the server generates a JWT containing the user's information, signs it, and sends it back to the client.
3.  **Storage and Transmission**: The client stores the JWT (preferably in an HttpOnly cookie or secure storage) and attaches it to the `Authorization` header using the `Bearer` schema (`Authorization: Bearer <token>`) for subsequent requests to the server.
4.  **Verification**: The server decodes the JWT and validates the signature and claims (like expiration). If valid, the server grants access to the requested resources.

---

### 💡 Key Benefits

*   **Stateless and Scalable**: Because the JWT contains all the necessary information, the server does not need to query a database or maintain session states in memory to authenticate a user. This makes scaling highly efficient.
*   **Compact**: Due to its small size, a JWT can easily be passed via URLs, POST parameters, or HTTP headers.
*   **Cross-Domain Support**: JWTs are easily shared and verified across different services and domains, making them ideal for Single Sign-On (SSO) systems and microservice architectures.

---

### 🛡️ Security Best Practices

While JWTs are highly secure when implemented correctly, their flexibility requires developers to adhere to strict security practices:

*   **Do Not Store Sensitive Data**: The payload of a standard JWT is only encoded, *not encrypted*. Anyone who intercepts the token can read its contents. Never store sensitive information like passwords or financial data in the payload.
*   **Keep Expiration Times Short**: Because stateless JWTs are difficult to revoke once issued, they should be given a short expiration time (`exp`) to limit the window an attacker has if a token is stolen. They are often paired with a "refresh token" to silently maintain user sessions.
*   **Always Verify the Signature and Algorithm**: You must definitively verify the signature and explicitly check that the algorithm (`alg`) used matches what your application expects. This prevents "algorithm confusion" or attacks where a malicious user changes the algorithm to `none` to bypass signature checks.
*   **Use HTTPS**: JWTs must always be transmitted over HTTPS/TLS to prevent attackers from intercepting the token via man-in-the-middle attacks.
