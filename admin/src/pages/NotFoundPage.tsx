/**
 * 404 Not Found Page
 */

import React from 'react';
import { Link } from 'react-router-dom';
import { Icon } from '../components/ui/Icon';
import { __ } from '../utils/i18n';

const NotFoundPage: React.FC = () => {
  return (
    <div className="ts-page">
      <div className="ts-empty-state" style={{ marginTop: '120px' }}>
        <div className="ts-empty-icon">
          <Icon name="Search" size={64} />
        </div>
        <h2>{__("Page Not Found")}</h2>
        <p>{__("The page you're looking for doesn't exist.")}</p>
        <Link to="/overview" className="ts-btn ts-btn-primary" style={{ marginTop: '24px' }}>
          {__("Go to Overview")}
        </Link>
      </div>
    </div>
  );
};

export default NotFoundPage;
