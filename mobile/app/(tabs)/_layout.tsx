import { Tabs } from 'expo-router';
import { BarChart3, CalendarDays, LayoutDashboard, ShoppingCart } from 'lucide-react-native';
import AppHeader from '../../components/AppHeader';

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: true,
        tabBarActiveTintColor: '#059669',
        tabBarInactiveTintColor: '#6b7280',
        tabBarStyle: { backgroundColor: '#ffffff' },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Dashboard',
          tabBarIcon: ({ color, size }) => (
            <LayoutDashboard color={color} size={size} />
          ),
          header: () => <AppHeader title="Dashboard" />,
        }}
      />
      <Tabs.Screen
        name="menu"
        options={{
          title: 'Menu',
          tabBarIcon: ({ color, size }) => (
            <CalendarDays color={color} size={size} />
          ),
          header: () => <AppHeader title="Menu Cycle" />,
        }}
      />
      <Tabs.Screen
        name="prep"
        options={{
          title: 'Prep & Accomp.',
          tabBarIcon: ({ color, size }) => (
            <BarChart3 color={color} size={size} />
          ),
          header: () => <AppHeader title="Prep & Accomplishment" />,
        }}
      />
      <Tabs.Screen
        name="procurement"
        options={{
          title: 'Procurement',
          tabBarIcon: ({ color, size }) => (
            <ShoppingCart color={color} size={size} />
          ),
          header: () => <AppHeader title="Procurement" />,
        }}
      />
    </Tabs>
  );
}
