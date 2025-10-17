import React, { useState } from "react";
import Zapier from "./zapier";
import { sprintf, __ } from '@wordpress/i18n';

const Integrate = ( { platform } ) => {

    const [usertabs, setUserTab] = useState('');
    const RenderIntegrate = () => {
        switch( platform ) {
            case 'zapier':
                return <Zapier/>;
            default :
               return <h2> {__('No Integrations Found', 'new-user-approve') } </h2>         
        }

    }

    return (

        <div>
           
            <div className="integration_list">
                {RenderIntegrate()}
            </div>
        </div>
    );

}


export default Integrate;