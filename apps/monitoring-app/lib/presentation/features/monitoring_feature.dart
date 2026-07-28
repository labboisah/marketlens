import 'package:flutter/material.dart';

class MonitoringFeature {
  const MonitoringFeature({
    required this.id,
    required this.title,
    required this.path,
    required this.icon,
  });

  final String id;
  final String title;
  final String path;
  final IconData icon;
}

const monitoringFeatures = <MonitoringFeature>[
  MonitoringFeature(id: 'dashboard', title: 'Dashboard', path: '/dashboard', icon: Icons.dashboard_outlined),
  MonitoringFeature(id: 'sales-summary', title: 'Sales Summary', path: '/sales-summary', icon: Icons.point_of_sale),
  MonitoringFeature(id: 'payment-summary', title: 'Payment Summary', path: '/payment-summary', icon: Icons.payments),
  MonitoringFeature(id: 'expenses', title: 'Expenses', path: '/expenses', icon: Icons.receipt_long),
  MonitoringFeature(id: 'stock-summary', title: 'Stock Summary', path: '/stock-summary', icon: Icons.inventory_2),
  MonitoringFeature(id: 'low-stock-alerts', title: 'Low-Stock Alerts', path: '/low-stock-alerts', icon: Icons.warning_amber),
  MonitoringFeature(id: 'customer-debts', title: 'Customer Debts', path: '/customer-debts', icon: Icons.account_balance_wallet),
  MonitoringFeature(id: 'cashier-performance', title: 'Cashier Performance', path: '/cashier-performance', icon: Icons.badge),
  MonitoringFeature(id: 'branch-performance', title: 'Branch Performance', path: '/branch-performance', icon: Icons.store),
  MonitoringFeature(id: 'security-alerts', title: 'Security Alerts', path: '/security-alerts', icon: Icons.security),
  MonitoringFeature(id: 'synchronization-status', title: 'Synchronization Status', path: '/synchronization-status', icon: Icons.sync),
  MonitoringFeature(id: 'notifications', title: 'Notifications', path: '/notifications', icon: Icons.notifications),
  MonitoringFeature(id: 'profile', title: 'Profile', path: '/profile', icon: Icons.person),
];
