# New business and workstation installation

1. In the Platform, confirm the correct business, branch and subscription. Obtain
   a fresh activation code on the Business Server device-activation screen.
2. Download the published Business Server installer from the Platform. Install
   it on the branch's main Windows computer. Use the signed release for that
   computer's architecture; x64 is the default download.
3. Activate against your actual Platform address and selected branch. Create or
   sign in to the local administrator. Configure backups, warehouses, payment
   methods and local roles. Keep the server computer available during trading.
4. Confirm Data Setup & Transfers exists. Fill and validate the templates using
   owner-approved reference codes and opening totals. Take the required backup
   before opening positions; retain the reconciliation and import receipts.
5. Download and install Business App on each workstation. Connect it to the
   Business Server's LAN address, complete pairing/authorization there and sign
   in with a local operational account. The Platform account is not the cashier
   login. Workstations never open or copy the Server's SQLite database.
6. Check each role's access, receipt printer and payment configuration. Reconcile
   product counts, stock, receivables and payables with the owner before trading.
   Exercise the sales workflow in a test installation before the live cutover.
7. Confirm backup verification, local operation without internet and subsequent
   synchronization to the correct Platform branch. Record installed versions,
   server address, backup/recovery arrangements and support contact privately.

For an additional workstation, repeat steps 5–7 with the existing Server.
Never activate a second authoritative Server for the same live branch as a way
to add a cashier workstation. Replacement Server recovery uses the documented
backup/recovery procedure and retirement of the old Server.
