import { Tabs } from 'expo-router';
import { CalendarDays, ChefHat, ClipboardCheck, LayoutDashboard, ShoppingCart } from 'lucide-react-native';
import AppHeader from '../../components/AppHeader';
import { MOBILE_THEME } from '../../lib/theme';

export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: true,
        tabBarActiveTintColor: MOBILE_THEME.colors.brand,
        tabBarInactiveTintColor: MOBILE_THEME.colors.muted,
        tabBarStyle: { backgroundColor: MOBILE_THEME.colors.surface, borderTopColor: MOBILE_THEME.colors.border, height: 64, paddingTop: 6, paddingBottom: 6 },
        tabBarLabelStyle: { fontSize: 10, fontWeight: '600' },
        tabBarHideOnKeyboard: true,
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
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
          title: 'Meal Prep',
          tabBarIcon: ({ color, size }) => (
            <ChefHat color={color} size={size} />
          ),
          header: () => <AppHeader title="Meal Preparation" />,
        }}
      />
      <Tabs.Screen
        name="accomplishments"
        options={{
          title: 'Accomplish',
          tabBarIcon: ({ color, size }) => (
            <ClipboardCheck color={color} size={size} />
          ),
          header: () => <AppHeader title="Accomplishments" />,
        }}
      />
      <Tabs.Screen
        name="procurement"
        options={{
          title: 'Purchase',
          tabBarIcon: ({ color, size }) => (
            <ShoppingCart color={color} size={size} />
          ),
          header: () => <AppHeader title="Procurement" />,
        }}
      />
    </Tabs>
  );
}
