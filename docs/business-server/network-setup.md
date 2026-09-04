# Business Server Network Setup

## LAN Binding

The default Business Server bind address is `127.0.0.1`. For trusted LAN Business Apps,
set `SHOP_LOCAL_API_HOST=0.0.0.0` and choose `SHOP_LOCAL_API_PORT` explicitly.
The administration Deployment screen displays reachable IPv4 addresses and the
Business App server URL.

## Windows Firewall

Allow the packaged MarketLens Business Server executable on **Private** networks only.
Do not open the port on Public networks. Business Apps still require an authorized
device and authenticated user; a firewall rule is not authorization.

## Client Connection

1. Put the Business App on the same trusted LAN.
2. Enter the Business Server URL displayed by Deployment settings.
3. Use the pairing code or approved device workflow.
4. Run the local connection test before enabling a terminal for daily work.

Discovery currently provides a server-name and manual pairing-code foundation.
Do not rely on multicast discovery until an authenticated discovery transport is
approved and implemented.